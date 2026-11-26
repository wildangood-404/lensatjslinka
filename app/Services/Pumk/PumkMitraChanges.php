<?php

namespace App\Services\Pumk;

use App\Models\PumkMitra;
use App\Models\PumkMitraDokumen;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use App\Services\Monitoring\PumkClassificationService;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class PumkMitraChanges
{
    public function __construct(private readonly PumkMitraInput $input) {}

    public function store(
        Request $request,
        PiutangCalculator $calculator,
        PumkActivityLogger $activity,
        PumkLoanDocumentService $documents,
        PumkClassificationService $classifications,
        PumkLoanSnapshotService $snapshots,
        PumkLoanSettlementService $settlement,
        string $sourceCategory = 'reguler',
    ): RedirectResponse {
        $data = $this->input->validateMitra($request);

        $existingArchived = PumkMitra::query()
            ->where('kategori_binaan', $sourceCategory)
            ->whereRaw('LOWER(nama_mitra) = ?', [mb_strtolower(trim($data['nama_mitra']))])
            ->whereDoesntHave('pinjamanAktif')
            ->first();
        if ($existingArchived) {
            return back()->withErrors([
                'nama_mitra' => 'Mitra dengan nama ini sudah ada di arsip. Aktifkan kembali dari daftar Mitra Lunas/Arsip agar histori tidak terduplikasi.',
            ])->withInput();
        }

        $newFilePaths = [];
        try {
            $mitra = DB::transaction(function () use ($request, $data, $calculator, $activity, $documents, $classifications, $snapshots, $settlement, $sourceCategory, &$newFilePaths): PumkMitra {
                $mitra = PumkMitra::create($this->input->mitraPayload($data) + [
                    'kategori_binaan' => $sourceCategory,
                    'source_key' => hash('sha256', 'manual-mitra|'.Str::uuid()),
                    'created_by' => auth('pumk')->id(),
                    'is_active' => true,
                ]);

                $pinjaman = $mitra->pinjaman()->create($this->input->pinjamanPayload($data) + [
                    'source_key' => hash('sha256', 'manual-pinjaman|'.Str::uuid()),
                    'created_by' => auth('pumk')->id(),
                    'status' => PumkPinjaman::STATUS_AKTIF,
                    'is_active' => true,
                ]);

                $snapshots->synchronize($pinjaman, $data, $calculator);
                if (($data['status_pinjaman'] ?? 'aktif') === 'lunas') {
                    $settlement->settle(
                        $mitra->id,
                        $pinjaman->id,
                        'Ditandai lunas saat input UMKM binaan.',
                        (int) auth('pumk')->id(),
                    );
                }
                $effective = CarbonImmutable::now('Asia/Jakarta');
                $classifications->record($mitra, null, 'sektor', $mitra->sektor_sumber, $effective, 'admin', (string) Str::uuid(), auth('pumk')->id());
                $classifications->record($mitra, null, 'wilayah', $mitra->wilayah_sumber, $effective, 'admin', (string) Str::uuid(), auth('pumk')->id());
                $classifications->record(null, $pinjaman, 'kolektibilitas', $pinjaman->kolektibilitas, $effective, 'estimate', (string) Str::uuid(), auth('pumk')->id());
                $this->storeContractDocuments($request, $pinjaman, $documents);
                $this->storeProfileDocuments($request, $mitra, $activity, $newFilePaths);
                $activity->record('create_loan', 'pumk_internal', 'Menambahkan Mitra dan pinjaman baru.', $pinjaman);

                return $mitra;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newFilePaths);
            throw $exception;
        }

        return redirect()
            ->route($sourceCategory === 'bri' ? 'pumk-admin.umkm-binaan-bri.show' : 'pumk-admin.umkm-binaan.show', $mitra)
            ->with('success', 'Mitra binaan berhasil ditambahkan.');
    }

    public function update(
        Request $request,
        PumkMitra $mitra,
        PiutangCalculator $calculator,
        PumkActivityLogger $activity,
        PumkLoanDocumentService $documents,
        PumkClassificationService $classifications,
        PumkLoanSnapshotService $snapshots,
        PumkLoanSettlementService $settlement,
        string $sourceCategory = 'reguler',
    ): RedirectResponse {
        abort_unless($mitra->kategori_binaan === $sourceCategory, 404);
        $request->validate([
            'pinjaman_id' => ['nullable', 'integer'],
            'new_loan' => ['prohibited'],
            'edit_reason' => ['nullable', 'string', 'max:1000'],
            'return_to_profile' => ['nullable', 'boolean'],
        ]);
        $data = $this->input->validateMitra($request);
        $newFilePaths = [];
        try {
            $loanId = DB::transaction(function () use ($request, $data, $mitra, $calculator, $activity, $documents, $classifications, $snapshots, $settlement, &$newFilePaths): int {
                $lockedMitra = PumkMitra::query()->lockForUpdate()->findOrFail($mitra->id);
                $id = $request->integer('pinjaman_id');
                $pinjaman = $id
                    ? $lockedMitra->pinjaman()->lockForUpdate()->findOrFail($id)
                    : ($lockedMitra->pinjaman()->where('status', PumkPinjaman::STATUS_AKTIF)->where('is_active', true)->latest('id')->lockForUpdate()->first()
                        ?? $lockedMitra->pinjaman()->latest('id')->lockForUpdate()->first());
                abort_if($pinjaman === null, 422, 'Pinjaman tidak ditemukan.');
                $archiveEdit = $pinjaman !== null && ($pinjaman->status !== PumkPinjaman::STATUS_AKTIF || ! $pinjaman->is_active);
                $financialFields = [
                    'tanggal_pencairan', 'tahun_pencairan', 'mulai_angsuran', 'selesai_angsuran',
                    'pinjaman_pokok', 'persen_bunga', 'pinjaman_bunga', 'nilai_angsuran_bulanan',
                    'kolektibilitas', 'sisa_pokok', 'sisa_bunga', 'total_sisa', 'denda', 'status_pinjaman',
                ];
                if ($archiveEdit) {
                    $request->validate(array_fill_keys($financialFields, ['prohibited']) + [
                        'edit_reason' => ['required', 'string', 'min:5', 'max:1000'],
                    ]);
                }
                // A document-only request must never erase existing nominal values or identity fields.
                $identityPayload = $this->input->mitraPayload($data);
                $identityKeys = [];
                foreach (array_keys($data) as $key) {
                    array_push($identityKeys, ...match ($key) {
                        'nik' => ['nik', 'no_ktp_encrypted'],
                        'no_ktp' => ['no_ktp_encrypted'],
                        'no_telepon' => ['no_telepon_encrypted'],
                        'no_rekening' => ['no_rekening_encrypted'],
                        'sektor_usaha' => ['sektor_sumber', 'sektor_usaha_id'],
                        default => [$key],
                    });
                }
                if (array_key_exists('sektor_usaha_id', $data)) {
                    $identityKeys[] = 'sektor_sumber';
                }
                if (array_key_exists('wilayah_id', $data)) {
                    $identityKeys[] = 'wilayah_sumber';
                }
                if (array_key_exists('kabupaten_kota', $data) || array_key_exists('provinsi', $data)) {
                    array_push($identityKeys, 'wilayah_id', 'wilayah_sumber', 'kabupaten_kota', 'provinsi');
                }
                if (array_key_exists('alamat_ktp_kabupaten_kota', $data)
                    || array_key_exists('alamat_ktp_provinsi', $data)
                    || array_key_exists('alamat_ktp_lengkap', $data)) {
                    array_push($identityKeys, 'alamat', 'wilayah_id', 'wilayah_sumber', 'kabupaten_kota', 'provinsi');
                }
                if (array_key_exists('alamat_domisili_sama_ktp', $data)
                    || array_key_exists('alamat_domisili_berbeda_ktp', $data)) {
                    $identityKeys[] = 'alamat_domisili_sama_ktp';
                    $identityKeys = [...$identityKeys, ...array_map(
                        fn (string $field): string => "alamat_domisili_{$field}",
                        ['provinsi', 'kabupaten_kota', 'kecamatan', 'desa', 'rt', 'rw', 'lengkap', 'share_lok'],
                    )];
                }
                if (array_key_exists('alamat_usaha_sama_ktp', $data)) {
                    $identityKeys = [...$identityKeys, ...array_map(
                        fn (string $field): string => "alamat_usaha_{$field}",
                        ['provinsi', 'kabupaten_kota', 'kecamatan', 'desa', 'rt', 'rw', 'lengkap', 'share_lok'],
                    )];
                }
                $lockedMitra->update(array_intersect_key($identityPayload, array_flip($identityKeys)));
                $sectorChanged = $lockedMitra->wasChanged(['sektor_sumber', 'sektor_usaha_id']);
                $regionChanged = $lockedMitra->wasChanged(['wilayah_sumber', 'wilayah_id', 'kabupaten_kota', 'provinsi']);
                $payload = array_intersect_key($this->input->pinjamanPayload($data), $data);
                if ($archiveEdit) {
                    $payload = array_diff_key($payload, array_flip($financialFields));
                }
                $pinjaman->update($payload);
                $qualityBefore = $pinjaman->kolektibilitas;
                if (! $archiveEdit) {
                    $snapshots->synchronize($pinjaman, $data, $calculator);
                    if (($data['status_pinjaman'] ?? $pinjaman->status_pinjaman) === 'lunas') {
                        $settlement->settle(
                            $lockedMitra->id,
                            $pinjaman->id,
                            'Ditandai lunas saat pembaruan data UMKM binaan.',
                            (int) auth('pumk')->id(),
                        );
                    }
                }
                $effective = CarbonImmutable::now('Asia/Jakarta');
                if ($sectorChanged) {
                    $classifications->record($lockedMitra, null, 'sektor', $lockedMitra->sektor_sumber, $effective, 'admin', (string) Str::uuid(), auth('pumk')->id());
                }
                if ($regionChanged) {
                    $classifications->record($lockedMitra, null, 'wilayah', $lockedMitra->wilayah_label, $effective, 'admin', (string) Str::uuid(), auth('pumk')->id());
                }
                if (! $archiveEdit && $qualityBefore !== $pinjaman->fresh()->kolektibilitas) {
                    $classifications->record(null, $pinjaman, 'kolektibilitas', $pinjaman->fresh()->kolektibilitas, $effective, 'estimate', (string) Str::uuid(), auth('pumk')->id());
                }
                $this->storeContractDocuments($request, $pinjaman, $documents);
                $this->storeProfileDocuments($request, $lockedMitra, $activity, $newFilePaths);
                $activity->record($archiveEdit ? 'edit_archived_loan' : 'update_loan',
                    'pumk_internal', $archiveEdit ? 'Memperbarui administrasi arsip; status dan saldo tetap.' : 'Memperbarui data Mitra atau fasilitas pinjaman.',
                    $pinjaman, metadata: ['reason' => $request->input('edit_reason')]);

                return $pinjaman->id;
            });
        } catch (Throwable $exception) {
            Storage::disk('local')->delete($newFilePaths);
            throw $exception;
        }

        $redirect = $request->boolean('return_to_profile')
            ? redirect()->route($sourceCategory === 'bri' ? 'pumk-admin.umkm-binaan-bri.show' : 'pumk-admin.umkm-binaan.show', $mitra)
            : redirect()->route(
                $sourceCategory === 'bri' ? 'pumk-admin.umkm-binaan-bri.card' : 'pumk-admin.mitra.show',
                $request->filled('pinjaman_id') ? [$mitra, 'pinjaman' => $loanId] : $mitra,
            );

        return $redirect->with('success', 'Data administrasi dan dokumen berhasil disimpan.');
    }

    private function storeContractDocuments(
        Request $request,
        PumkPinjaman $pinjaman,
        PumkLoanDocumentService $documents,
    ): void {
        foreach (array_keys(PumkPinjamanDokumen::TYPES) as $type) {
            $file = $request->file("dokumen_{$type}");
            if ($file !== null) {
                $documents->replace($pinjaman, $type, $file);
            }
        }
    }

    /** @param list<string> $newFilePaths */
    private function storeProfileDocuments(
        Request $request,
        PumkMitra $mitra,
        PumkActivityLogger $activity,
        array &$newFilePaths,
    ): void {
        $photo = $request->file('pasfoto');
        if ($photo !== null) {
            $path = $photo->store("pumk/mitra/{$mitra->id}/pasfoto", 'local');
            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages(['pasfoto' => 'Pasfoto gagal disimpan.']);
            }
            $newFilePaths[] = $path;
            $oldPath = $mitra->pasfoto;
            $mitra->update(['pasfoto' => $path]);
            $activity->record('upload_partner_photo', 'pumk_internal', 'Mengunggah pasfoto pemilik UMKM binaan.', $mitra);
            if ($oldPath && $oldPath !== $path) {
                DB::afterCommit(fn () => Storage::disk('local')->delete($oldPath));
            }
        }

        foreach ($request->file('dokumen_pendukung', []) as $file) {
            $path = $file->store("pumk/mitra/{$mitra->id}/dokumen", 'local');
            if (! is_string($path) || $path === '') {
                throw ValidationException::withMessages(['dokumen_pendukung' => 'Dokumen pendukung gagal disimpan.']);
            }
            $newFilePaths[] = $path;
            $document = PumkMitraDokumen::create([
                'mitra_id' => $mitra->id,
                'file_path' => $path,
                'nama_file_asli' => mb_substr($file->getClientOriginalName(), 0, 255),
                'mime_type' => (string) ($file->getMimeType() ?: 'application/octet-stream'),
                'file_size' => (int) $file->getSize(),
                'uploaded_by' => auth('pumk')->id(),
                'uploaded_at' => now(),
            ]);
            $activity->record(
                'upload_partner_document',
                'pumk_internal',
                'Mengunggah dokumen pendukung UMKM binaan.',
                $document,
                metadata: ['nama_file' => $document->nama_file_asli],
            );
        }
    }
}
