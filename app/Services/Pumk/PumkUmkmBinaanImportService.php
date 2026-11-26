<?php

namespace App\Services\Pumk;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Services\Monitoring\MonitoringXlsxReader;
use App\Services\Monitoring\PumkClassificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class PumkUmkmBinaanImportService
{
    private const HEADERS = [
        'umkm_binaan',
        'alamat',
        'wilayah',
        'pemilik',
        'kontak',
        'sektor',
        'pencairan',
        'tahun',
        'kolektibilitas',
        'sisa_pinjaman_(pokok_&_bunga)',
        'sisa_pinjaman_pokok',
        'sisa_pinjaman_bunga',
        'denda',
        'status',
    ];

    public function __construct(
        private readonly MonitoringXlsxReader $xlsxReader,
        private readonly PumkImportValueParser $values,
        private readonly PumkMitraInput $input,
        private readonly PumkLoanSnapshotService $snapshots,
        private readonly PumkClassificationService $classifications,
        private readonly PumkLoanSettlementService $settlement,
        private readonly PumkActivityLogger $activity,
    ) {}

    /** @return array{imported:int,duplicate:int,failed:int,rows:list<array{row:int,message:string}>} */
    public function import(UploadedFile $file, string $sourceCategory = 'reguler'): array
    {
        $rows = $this->readRows($file);
        $names = PumkMitra::query()->where('kategori_binaan', $sourceCategory)->pluck('nama_mitra')
            ->map(fn (string $name): string => mb_strtolower(trim($name)))
            ->flip();
        $result = ['imported' => 0, 'duplicate' => 0, 'failed' => 0, 'rows' => []];

        foreach ($rows as $sourceRow) {
            $data = $this->mapRow($sourceRow['values']);
            $nameKey = mb_strtolower(trim((string) ($data['nama_mitra'] ?? '')));
            if ($nameKey !== '' && $names->has($nameKey)) {
                $result['duplicate']++;
                $this->addMessage($result, $sourceRow['row'], 'Nama UMKM binaan sudah terdaftar; baris dilewati.');

                continue;
            }

            $validator = Validator::make($data, [
                'nama_mitra' => ['required', 'string', 'max:255'],
                'alamat_ktp_lengkap' => ['nullable', 'string', 'max:3000'],
                'alamat_ktp_kabupaten_kota' => ['nullable', 'string', 'max:255'],
                'nama_pemilik' => ['nullable', 'string', 'max:255'],
                'no_telepon' => ['nullable', 'string', 'max:64'],
                'sektor_usaha' => ['nullable', 'string', 'max:255'],
                'pinjaman_pokok' => ['nullable', 'numeric', 'min:0'],
                'tahun_pencairan' => ['nullable', 'integer', 'digits:4', 'between:1900,2200'],
                'kolektibilitas' => ['nullable', 'in:lancar,kurang_lancar,diragukan,macet,bermasalah'],
                'total_sisa' => ['nullable', 'numeric'],
                'sisa_pokok' => ['nullable', 'numeric'],
                'sisa_bunga' => ['nullable', 'numeric'],
                'denda' => ['nullable', 'numeric', 'min:0'],
                'status_pinjaman' => ['nullable', 'in:aktif,lunas,reschedule,bermasalah'],
            ]);
            if ($validator->fails()) {
                $result['failed']++;
                $this->addMessage($result, $sourceRow['row'], implode(' ', $validator->errors()->all()));

                continue;
            }

            try {
                DB::transaction(function () use ($data, $sourceRow, $nameKey, $sourceCategory): void {
                    if (PumkMitra::query()->where('kategori_binaan', $sourceCategory)->whereRaw('LOWER(nama_mitra) = ?', [$nameKey])->lockForUpdate()->exists()) {
                        throw ValidationException::withMessages(['duplicate' => 'Nama UMKM binaan sudah terdaftar; baris dilewati.']);
                    }

                    $mitra = PumkMitra::query()->create($this->input->mitraPayload($data) + [
                        'kategori_binaan' => $sourceCategory,
                        'source_key' => hash('sha256', 'umkm-import|'.auth('pumk')->id().'|'.$sourceRow['row'].'|'.Str::uuid()),
                        'created_by' => auth('pumk')->id(),
                        'is_active' => true,
                    ]);
                    $loan = $mitra->pinjaman()->create($this->input->pinjamanPayload($data) + [
                        'source_key' => hash('sha256', 'umkm-import-loan|'.$mitra->id.'|'.Str::uuid()),
                        'created_by' => auth('pumk')->id(),
                        'status' => PumkPinjaman::STATUS_AKTIF,
                        'is_active' => true,
                    ]);
                    $this->snapshots->saveImported($loan, $data);
                    $effective = CarbonImmutable::now('Asia/Jakarta');
                    $sourceRef = 'umkm-import:'.Str::uuid();
                    $this->classifications->record($mitra, null, 'sektor', $mitra->sektor_sumber, $effective, 'import', $sourceRef, auth('pumk')->id());
                    $this->classifications->record($mitra, null, 'wilayah', $mitra->wilayah_sumber, $effective, 'import', $sourceRef, auth('pumk')->id());
                    $this->classifications->record(null, $loan, 'kolektibilitas', $loan->kolektibilitas, $effective, 'import', $sourceRef, auth('pumk')->id());
                    if (($data['status_pinjaman'] ?? 'aktif') === 'lunas') {
                        $this->settlement->settle(
                            $mitra->id,
                            $loan->id,
                            'Ditandai lunas saat impor UMKM binaan.',
                            (int) auth('pumk')->id(),
                        );
                    }
                    $this->activity->record('import_umkm_binaan', 'pumk_internal', 'Mengimpor data UMKM binaan dari Excel/CSV.', $loan, metadata: [
                        'row' => $sourceRow['row'],
                    ]);

                }, 3);

                $names->put($nameKey, true);
                $result['imported']++;
            } catch (ValidationException $exception) {
                $result['failed']++;
                $this->addMessage($result, $sourceRow['row'], implode(' ', $exception->errors()['duplicate'] ?? $exception->validator->errors()->all()));
            } catch (Throwable $exception) {
                report($exception);
                $result['failed']++;
                $this->addMessage($result, $sourceRow['row'], 'Baris tidak dapat disimpan karena terjadi kesalahan data atau proses.');
            }
        }

        return $result;
    }

    /**
     * @return list<array{row:int,values:array<string,?string>}>
     */
    private function readRows(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'csv'], true) || $file->getSize() > 10 * 1024 * 1024) {
            throw ValidationException::withMessages(['import_file' => 'Unggah file .xlsx atau .csv maksimal 10 MB.']);
        }

        if ($extension === 'xlsx') {
            try {
                $workbook = $this->xlsxReader->read($file->getRealPath(), ['UMKM Binaan', 'Sheet1']);
            } catch (RuntimeException $exception) {
                throw ValidationException::withMessages(['import_file' => $exception->getMessage()]);
            }
            $sheet = $workbook['UMKM Binaan'] ?? $workbook['Sheet1'] ?? null;
            if ($sheet === null) {
                throw ValidationException::withMessages(['import_file' => 'Worksheet harus bernama "UMKM Binaan" atau "Sheet1".']);
            }
            $headers = $sheet['headers'];
            $rows = $sheet['rows'];
        } else {
            [$headers, $rows] = $this->readCsv($file->getRealPath());
        }

        $missingHeaders = array_values(array_diff(self::HEADERS, $headers));
        if ($missingHeaders !== []) {
            throw ValidationException::withMessages([
                'import_file' => 'Header template tidak lengkap. Kolom yang wajib ada: '.implode(', ', $missingHeaders).'.',
            ]);
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['import_file' => 'File tidak berisi baris data untuk diimpor.']);
        }
        if (count($rows) > 5000) {
            throw ValidationException::withMessages(['import_file' => 'Maksimal 5.000 baris data dapat diimpor sekaligus.']);
        }

        return $rows;
    }

    /**
     * @return array{list<string>,list<array{row:int,values:array<string,?string>}>}
     */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw ValidationException::withMessages(['import_file' => 'File CSV tidak dapat dibaca.']);
        }

        try {
            $rawHeaders = fgetcsv($handle);
            if (! is_array($rawHeaders)) {
                throw ValidationException::withMessages(['import_file' => 'File CSV kosong atau tidak memiliki header.']);
            }
            $headers = array_map(fn ($header): string => $this->normalizeHeader((string) $header), $rawHeaders);
            $namedHeaders = array_values(array_filter($headers, fn (string $header): bool => $header !== ''));
            if (count(array_unique($namedHeaders)) !== count($namedHeaders)) {
                throw ValidationException::withMessages(['import_file' => 'File CSV memiliki header kolom yang duplikat.']);
            }
            $rows = [];
            $rowNumber = 1;
            while (($rawValues = fgetcsv($handle)) !== false) {
                $rowNumber++;
                if ($rowNumber > 5001) {
                    throw ValidationException::withMessages(['import_file' => 'Maksimal 5.000 baris data dapat diimpor sekaligus.']);
                }
                $values = [];
                foreach ($headers as $index => $header) {
                    $values[$header] = isset($rawValues[$index]) ? trim((string) $rawValues[$index]) : null;
                }
                if (collect($values)->contains(fn ($value): bool => trim((string) $value) !== '')) {
                    $rows[] = ['row' => $rowNumber, 'values' => $values];
                }
            }

            return [$headers, $rows];
        } finally {
            fclose($handle);
        }
    }

    /** @param array<string,?string> $values
     * @return array<string,mixed>
     */
    private function mapRow(array $values): array
    {
        $collectibility = $this->enumValue($values['kolektibilitas'] ?? null, [
            'lancar' => 'lancar',
            'kurang lancar' => 'kurang_lancar',
            'diragukan' => 'diragukan',
            'macet' => 'macet',
            'bermasalah' => 'bermasalah',
        ]);
        $status = $this->enumValue($values['status'] ?? null, [
            'aktif' => 'aktif',
            'lunas' => 'lunas',
            'reschedule' => 'reschedule',
            'bermasalah' => 'bermasalah',
        ]);

        return [
            'nama_mitra' => trim((string) ($values['umkm_binaan'] ?? '')),
            'alamat_ktp_lengkap' => $this->nullableString($values['alamat'] ?? null),
            'alamat_ktp_kabupaten_kota' => $this->nullableString($values['wilayah'] ?? null),
            'alamat_domisili_berbeda_ktp' => '0',
            'alamat_usaha_sama_ktp' => '1',
            'nama_pemilik' => $this->nullableString($values['pemilik'] ?? null),
            'no_telepon' => $this->nullableString($values['kontak'] ?? null),
            'sektor_usaha' => $this->nullableString($values['sektor'] ?? null),
            'pinjaman_pokok' => $this->amount($values['pencairan'] ?? null),
            'tahun_pencairan' => $this->nullableString($values['tahun'] ?? null),
            'kolektibilitas' => $collectibility,
            'total_sisa' => $this->amount($values['sisa_pinjaman_(pokok_&_bunga)'] ?? null),
            'sisa_pokok' => $this->amount($values['sisa_pinjaman_pokok'] ?? null),
            'sisa_bunga' => $this->amount($values['sisa_pinjaman_bunga'] ?? null),
            'denda' => $this->amount($values['denda'] ?? null) ?? '0.00',
            'status_pinjaman' => $status ?? 'aktif',
            'financial_snapshot' => '1',
        ];
    }

    private function amount(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }

        $groupedNumber = preg_replace('/[^0-9.\-]/', '', $value) ?? '';
        if (! str_contains($value, ',') && preg_match('/^-?\d{1,3}(?:\.\d{3})+$/', $groupedNumber)) {
            $value = str_replace('.', '', $groupedNumber);
        }

        return $this->values->sourceMoney($value) ?? $value;
    }

    /** @param array<string,string> $mapping */
    private function enumValue(?string $value, array $mapping): ?string
    {
        $normalized = mb_strtolower(trim((string) $value));

        return $mapping[$normalized] ?? ($normalized === '' ? null : $normalized);
    }

    private function nullableString(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function normalizeHeader(string $value): string
    {
        $value = preg_replace('/^\xEF\xBB\xBF/', '', trim($value)) ?? '';

        return strtolower(preg_replace('/\s+/u', '_', $value) ?? $value);
    }

    /** @param array{imported:int,duplicate:int,failed:int,rows:list<array{row:int,message:string}>} $result */
    private function addMessage(array &$result, int $row, string $message): void
    {
        if (count($result['rows']) < 100) {
            $result['rows'][] = ['row' => $row, 'message' => $message];
        }
    }
}
