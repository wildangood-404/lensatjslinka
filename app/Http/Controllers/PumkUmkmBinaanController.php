<?php

namespace App\Http\Controllers;

use App\Models\PumkMitra;
use App\Models\PumkMitraDokumen;
use App\Models\PumkMonitoringPosition;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use App\Models\UmkmMonitoring;
use App\Services\Pumk\PumkActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class PumkUmkmBinaanController extends Controller
{
    private const PREVIEWABLE_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public function show(Request $request, PumkMitra $mitra): View
    {
        $sourceCategory = $this->sourceCategory($request);
        abort_unless($mitra->kategori_binaan === $sourceCategory, 404);
        $mitra->load([
            'wilayah:id,nama',
            'sektorUsaha:id,nama',
            'dokumenPendukung',
            'pinjaman' => fn ($query) => $query
                ->with([
                    'angsuran' => fn ($angsuran) => $angsuran->oldest('periode')->oldest('id'),
                    'dokumenKontrak',
                ])
                ->latest('tanggal_pencairan')
                ->latest('id'),
        ]);
        $monitoringHistory = PumkMonitoringPosition::query()
            ->where('mitra_id', $mitra->id)
            ->whereHas('report', fn ($query) => $query->whereDate('as_of_date', '<=', today()->toDateString()))
            ->with([
                'report:id,as_of_date,revision,generated_at,needs_reconcile',
                'pinjaman:id,mitra_id,spj_awal',
            ])
            ->get()
            ->sortByDesc(fn (PumkMonitoringPosition $position) => $position->report?->as_of_date?->toDateString())
            ->values();
        $monitoringRecords = $mitra->monitorings()
            ->latest('periode_tahun')
            ->latest('created_at')
            ->get();

        return view('pumk-admin.umkm-binaan.show', compact('mitra', 'monitoringHistory', 'monitoringRecords', 'sourceCategory'));
    }

    public function storeMonitoring(
        Request $request,
        PumkMitra $mitra,
        PumkActivityLogger $activity,
    ): RedirectResponse {
        abort_unless($mitra->kategori_binaan === 'reguler', 404);

        $validated = $request->validate([
            'periode_tahun' => ['required', 'integer', 'between:1900,2100'],
            'jenis_kunjungan' => ['required', 'string', 'in:Kunjungan Rutin,Monitoring Perkembangan Usaha,Pembinaan & Pendampingan,Penagihan & Restrukturisasi,Lainnya'],
            'jenis_kunjungan_custom' => ['nullable', 'required_if:jenis_kunjungan,Lainnya', 'string', 'max:120'],
            'tindak_lanjut' => ['required', 'string', 'max:10000'],
            'keterangan' => ['nullable', 'string', 'max:10000'],
            'dokumentasi' => ['nullable', 'array'],
            'dokumentasi.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:10240'],
        ]);

        $storedDocuments = [];
        $storedPaths = [];
        $disk = Storage::disk('local');

        try {
            foreach ($validated['dokumentasi'] ?? [] as $file) {
                $path = $file->store("umkm-monitorings/{$mitra->id}", 'local');
                if (! is_string($path) || $path === '') {
                    throw new RuntimeException('Berkas dokumentasi monitoring tidak dapat disimpan.');
                }

                $storedPaths[] = $path;
                $storedDocuments[] = [
                    'path' => $path,
                    'name' => $file->getClientOriginalName(),
                    'mime' => $file->getMimeType() ?: $file->getClientMimeType(),
                ];
            }

            DB::transaction(function () use ($mitra, $validated, $storedDocuments, $activity): void {
                $jenisKunjungan = $validated['jenis_kunjungan'] === 'Lainnya'
                    ? trim($validated['jenis_kunjungan_custom'])
                    : $validated['jenis_kunjungan'];

                $monitoring = $mitra->monitorings()->create([
                    'periode_tahun' => $validated['periode_tahun'],
                    'jenis_kunjungan' => $jenisKunjungan,
                    'tindak_lanjut' => $validated['tindak_lanjut'],
                    'keterangan' => $validated['keterangan'] ?? null,
                    'dokumentasi' => $storedDocuments === [] ? null : $storedDocuments,
                ]);

                $activity->record(
                    'create_monitoring_visit',
                    'pumk_internal',
                    'Menyimpan catatan kunjungan monitoring UMKM binaan.',
                    $monitoring,
                    metadata: ['mitra_id' => $mitra->id],
                );
            });
        } catch (Throwable $exception) {
            foreach ($storedPaths as $path) {
                if (! $disk->delete($path)) {
                    throw new RuntimeException('Berkas monitoring gagal dibersihkan setelah penyimpanan dibatalkan.', 0, $exception);
                }
            }

            throw $exception;
        }

        return redirect()
            ->route('pumk-admin.umkm-binaan.show', ['mitra' => $mitra, 'tab' => 'monitoring'])
            ->with('success', 'Update monitoring berhasil disimpan.');
    }

    public function viewMonitoringDocument(
        PumkMitra $mitra,
        UmkmMonitoring $monitoring,
        int $index,
    ): StreamedResponse {
        $this->assertMitraCategory($mitra);
        abort_unless($mitra->kategori_binaan === 'reguler' && $monitoring->mitra_id === $mitra->id, 404);

        $document = $monitoring->dokumentasi[$index] ?? null;
        abort_unless(is_array($document) && filled($document['path'] ?? null), 404);

        $disk = Storage::disk('local');
        $path = $document['path'];
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        abort_unless(in_array($mime, self::PREVIEWABLE_MIME_TYPES, true), 415);

        return $disk->response($path, basename($path), [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ], 'inline');
    }

    public function photo(PumkMitra $mitra): StreamedResponse
    {
        $this->assertMitraCategory($mitra);
        $disk = Storage::disk('local');
        abort_unless(filled($mitra->pasfoto) && $disk->exists($mitra->pasfoto), 404);
        $mime = $disk->mimeType($mitra->pasfoto) ?: 'application/octet-stream';

        return $disk->response($mitra->pasfoto, 'pasfoto-umkm-binaan', [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="pasfoto-umkm-binaan"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function downloadDocument(PumkMitra $mitra, PumkMitraDokumen $document): StreamedResponse
    {
        $this->assertMitraCategory($mitra);
        abort_unless($document->mitra_id === $mitra->id, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->nama_file_asli, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function viewDocument(PumkMitra $mitra, PumkMitraDokumen $document): StreamedResponse
    {
        $this->assertMitraCategory($mitra);
        abort_unless($document->mitra_id === $mitra->id, 404);

        return $this->inlineDocument($document->file_path, $document->nama_file_asli);
    }

    public function downloadLoanDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): StreamedResponse {
        $this->assertMitraCategory($mitra);
        abort_unless($pinjaman->mitra_id === $mitra->id && $document->pinjaman_id === $pinjaman->id, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);

        return $disk->download($document->file_path, $document->nama_file_asli, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function viewLoanDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): StreamedResponse {
        $this->assertMitraCategory($mitra);
        abort_unless($pinjaman->mitra_id === $mitra->id && $document->pinjaman_id === $pinjaman->id, 404);

        return $this->inlineDocument($document->file_path, $document->nama_file_asli);
    }

    public function destroyDocument(
        PumkMitra $mitra,
        PumkMitraDokumen $document,
        PumkActivityLogger $activity,
    ): RedirectResponse {
        $this->assertMitraCategory($mitra);
        abort_unless($document->mitra_id === $mitra->id, 404);
        $disk = Storage::disk('local');
        abort_unless($disk->exists($document->file_path), 404);
        if (! $disk->delete($document->file_path)) {
            throw new RuntimeException('Dokumen UMKM binaan tidak dapat dihapus dari penyimpanan.');
        }

        DB::transaction(function () use ($document, $activity): void {
            $document->delete();
            $activity->record('delete_partner_document', 'pumk_internal', 'Menghapus dokumen pendukung UMKM binaan.', $document);
        });

        return back()->with('success', 'Dokumen pendukung berhasil dihapus.');
    }

    private function inlineDocument(string $path, string $filename): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless($disk->exists($path), 404);
        $mime = $disk->mimeType($path) ?: 'application/octet-stream';
        abort_unless(in_array($mime, self::PREVIEWABLE_MIME_TYPES, true), 415, 'Format berkas ini tidak dapat dipratinjau; silakan unduh berkas.');

        return $disk->response($path, $filename, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ], 'inline');
    }

    private function sourceCategory(Request $request): string
    {
        return $request->routeIs('pumk-admin.umkm-binaan-bri.*') ? 'bri' : 'reguler';
    }

    private function assertMitraCategory(PumkMitra $mitra): void
    {
        abort_unless($mitra->kategori_binaan === $this->sourceCategory(request()), 404);
    }
}
