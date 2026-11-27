<?php

namespace App\Services\Pumk;

use App\Models\PumkBriIdentityReview;
use App\Models\PumkBriSnapshotBulanan;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use RuntimeException;
use Throwable;

class PumkBriMonthlySnapshotImportService
{
    public const TEMPLATE_HEADERS = [
        'Kode Unit',
        'BRI Unit',
        'Nama Mitra Binaan',
        'Alamat',
        'Provinsi',
        'Kabupaten/ Kota',
        'Sektor Ekonomi BUMN',
        'Jangka Waktu',
        'Tanggal Realisasi',
        'Tanggal Jatuh Tempo',
        'Jumlah Realisasi (Rp)',
        'Outstanding (Rp)',
        'Kolektabilitas BUMN',
        'Tanggal Lunas',
        'Jenis Usaha',
        'Accrued Interest',
        'Angsuran Bunga yang Diterima Giro',
    ];

    private const MAX_FILE_BYTES = 15 * 1024 * 1024;

    public const MAX_ROWS = 100_000;

    public function __construct(
        private readonly PumkBriWorkbookReader $workbookReader,
        private readonly PumkBriMonthlySnapshotParser $parser,
        private readonly PumkBriSnapshotParser $identityParser,
        private readonly PumkBriSnapshotPeriodWriter $writer,
        private readonly PumkBriFacilityStatusService $facilityStatus,
    ) {}

    /** @return array{status:string,bulan:int,tahun:int,baris:int,total_saldo:string,pesan:string} */
    public function import(string $path, string $extension, int $month, int $year): array
    {
        if ($month < 1 || $month > 12 || $year < 1900 || $year > 2100) {
            throw new InvalidArgumentException('Periode laporan tidak valid.');
        }

        $size = is_file($path) ? filesize($path) : false;
        if ($size === false || $size <= 0 || $size > self::MAX_FILE_BYTES) {
            throw new InvalidArgumentException('Ukuran file tidak valid atau melebihi 15 MB.');
        }

        $rows = $this->readRows($path, strtolower($extension));
        if (count($rows) > self::MAX_ROWS + 20) {
            throw new InvalidArgumentException('Jumlah baris data melebihi batas 100.000 baris.');
        }
        $parsed = $this->parser->parse($rows);
        $sheetName = sprintf('%04d-%02d', $year, $month);

        try {
            $this->writer->persistPeriod($sheetName, $month, $year, $parsed['records'], true);
        } catch (PumkBriIdentityNeedsReview $exception) {
            foreach ($exception->cases as $case) {
                $profile = $case['profile'];
                PumkBriIdentityReview::query()->updateOrCreate(
                    [
                        'tahun' => $year,
                        'bulan' => $month,
                        'source_sheet' => $sheetName,
                        'source_row' => $case['source_row'],
                    ],
                    [
                        'source_profile' => $profile,
                        'source_fingerprint' => $this->identityParser->profileFingerprint($profile),
                        'candidate_fasilitas_ids' => $case['candidates'],
                        'reason' => $case['reason'],
                        'status' => 'pending',
                        'resolved_fasilitas_id' => null,
                        'resolved_mitra_id' => null,
                        'resolution_action' => null,
                    ],
                );
            }

            return [
                'status' => 'needs_review',
                'bulan' => $month,
                'tahun' => $year,
                'baris' => 0,
                'total_saldo' => '0.00',
                'pesan' => $exception->getMessage().' Baris: '.implode(', ', array_column($exception->cases, 'source_row')).'.',
            ];
        }

        $this->facilityStatus->sync();

        return [
            'status' => 'imported',
            'bulan' => $month,
            'tahun' => $year,
            'baris' => count($parsed['records']),
            'total_saldo' => $parsed['total'],
            'pesan' => 'Periode berhasil diperbarui.',
        ];
    }

    /** @return array<int|string, array<string, string|null>> */
    private function readRows(string $path, string $extension): array
    {
        return match ($extension) {
            'xlsx' => $this->readXlsx($path),
            'xls' => $this->readXls($path),
            'csv' => $this->readCsv($path),
            default => throw new InvalidArgumentException('Format file harus .xlsx, .xls, atau .csv.'),
        };
    }

    /** @return array<string, array<int, string|null>> */
    private function readXlsx(string $path): array
    {
        try {
            $sheets = $this->workbookReader->read($path);
        } catch (RuntimeException $exception) {
            throw new InvalidArgumentException($exception->getMessage(), 0, $exception);
        }

        if ($sheets === []) {
            throw new InvalidArgumentException('Workbook tidak memiliki worksheet.');
        }

        return reset($sheets);
    }

    /** @return array<int, array<string, string|null>> */
    private function readXls(string $path): array
    {
        try {
            $reader = IOFactory::createReaderForFile($path);
            $reader->setReadDataOnly(true);
            $reader->setReadFilter(new class implements IReadFilter
            {
                public function readCell(string $columnAddress, int $row, string $worksheetName = ''): bool
                {
                    return $row <= PumkBriMonthlySnapshotImportService::MAX_ROWS + 20
                        && in_array($columnAddress, range('A', 'Q'), true);
                }
            });
            $spreadsheet = $reader->load($path);
            $rows = $spreadsheet->getSheet(0)->toArray(null, true, true, true);
            $spreadsheet->disconnectWorksheets();

            return $rows;
        } catch (Throwable $exception) {
            Log::warning('Workbook XLS UMKM BRI tidak dapat dibaca.', ['exception_type' => $exception::class]);
            throw new InvalidArgumentException('File .xls tidak dapat dibaca atau strukturnya tidak sesuai.', 0, $exception);
        }
    }

    /** @return array<int, array<string, string|null>> */
    private function readCsv(string $path): array
    {
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            throw new InvalidArgumentException('File CSV tidak dapat dibaca.');
        }

        try {
            $firstLine = fgets($handle);
            if ($firstLine === false) {
                throw new InvalidArgumentException('File CSV kosong.');
            }
            $delimiter = substr_count($firstLine, ';') > substr_count($firstLine, ',') ? ';' : ',';
            rewind($handle);
            $rows = [];
            $rowNumber = 0;
            while (($values = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
                $rowNumber++;
                if ($rowNumber > self::MAX_ROWS + 20) {
                    throw new InvalidArgumentException('Jumlah baris data melebihi batas 100.000 baris.');
                }
                if ($rowNumber === 1 && isset($values[0])) {
                    $values[0] = preg_replace('/^\xEF\xBB\xBF/', '', (string) $values[0]);
                }

                $row = [];
                foreach (array_slice($values, 0, 17) as $index => $value) {
                    $row[$this->columnName($index + 1)] = $value === null ? null : trim((string) $value);
                }
                $rows[$rowNumber] = $row;
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }
}
