<?php

namespace App\Http\Controllers;

use App\Models\PumkMitra;
use App\Models\PumkMitraDokumen;
use App\Models\PumkMonitoringPosition;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use App\Services\Pumk\PumkActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PumkUmkmBinaanController extends Controller
{
    private const PREVIEWABLE_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public function show(PumkMitra $mitra): View
    {
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

        return view('pumk-admin.umkm-binaan.show', compact('mitra', 'monitoringHistory'));
    }

    public function photo(PumkMitra $mitra): StreamedResponse
    {
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
        abort_unless($document->mitra_id === $mitra->id, 404);

        return $this->inlineDocument($document->file_path, $document->nama_file_asli);
    }

    public function downloadLoanDocument(
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): StreamedResponse {
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
        abort_unless($pinjaman->mitra_id === $mitra->id && $document->pinjaman_id === $pinjaman->id, 404);

        return $this->inlineDocument($document->file_path, $document->nama_file_asli);
    }

    public function destroyDocument(
        PumkMitra $mitra,
        PumkMitraDokumen $document,
        PumkActivityLogger $activity,
    ): RedirectResponse {
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
}
