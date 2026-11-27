<?php

namespace App\Http\Controllers;

use App\Models\PumkBriAnnualFundSummary;
use App\Models\PumkBriPeriod;
use App\Models\PumkBriRealization;
use App\Services\Pumk\PumkActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PumkBriController extends Controller
{
    private const INLINE_MIME_TYPES = [
        'application/pdf',
        'image/jpeg',
        'image/png',
        'image/gif',
        'image/webp',
    ];

    public function index(): View
    {
        $periods = PumkBriPeriod::query()
            ->with('realisasi')
            ->orderByDesc('periode_tahun')
            ->get();
        $annualFundSummaries = PumkBriAnnualFundSummary::query()
            ->orderByDesc('tahun')
            ->get();
        $totalBudget = $periods->reduce(
            fn (string $sum, PumkBriPeriod $period): string => bcadd($sum, (string) $period->anggaran_rka, 0),
            '0',
        );
        $totalRealization = $periods->flatMap->realisasi->reduce(
            fn (string $sum, PumkBriRealization $realization): string => bcadd($sum, (string) $realization->nominal, 0),
            '0',
        );

        return view('pumk-admin.bri.index', [
            'periods' => $periods,
            'annualFundSummaries' => $annualFundSummaries,
            'totalBudget' => $totalBudget,
            'totalRealization' => $totalRealization,
            'defaultYear' => now()->year,
        ]);
    }

    public function storePeriod(Request $request, PumkActivityLogger $activity): RedirectResponse
    {
        $data = $request->validate([
            'periode_tahun' => ['required', 'integer', 'digits:4', 'between:1900,2100'],
            'anggaran_rka' => ['required', 'string', 'max:80'],
        ]);
        $amount = $this->rupiah($data['anggaran_rka'], 'anggaran_rka');
        $year = (int) $data['periode_tahun'];

        $period = DB::transaction(fn (): PumkBriPeriod => PumkBriPeriod::query()->updateOrCreate(
            ['periode_tahun' => $year],
            ['anggaran_rka' => $amount],
        ));

        $activity->record(
            $period->wasRecentlyCreated ? 'create_bri_period' : 'update_bri_period',
            'pumk_bri',
            'Menyimpan periode tahun dan anggaran RKA PUMK BRI.',
            $period,
            metadata: ['periode_tahun' => $year],
        );

        return redirect()->route('pumk-admin.umkm-binaan-bri.pumk-bri.index')
            ->with('success', "Periode tahun {$year} berhasil disimpan.");
    }

    public function storeRealization(Request $request, PumkActivityLogger $activity): RedirectResponse
    {
        $data = $request->validate([
            'pumk_bri_period_id' => ['required', 'integer', 'exists:pumk_bri_periods,id'],
            'uraian' => ['required', 'string', 'max:3000'],
            'waktu' => ['required', 'date'],
            'pic' => ['required', 'string', 'max:255'],
            'nominal' => ['required', 'string', 'max:80'],
            'dokumen' => [
                'nullable',
                'file',
                'mimes:pdf,jpg,jpeg,png,gif,webp,doc,docx,xls,xlsx,ppt,pptx',
                'max:10240',
            ],
        ]);
        $amount = $this->rupiah($data['nominal'], 'nominal');
        $file = $request->file('dokumen');
        $path = null;

        try {
            $realization = DB::transaction(function () use ($data, $file, $amount, &$path): PumkBriRealization {
                $period = PumkBriPeriod::query()->findOrFail((int) $data['pumk_bri_period_id']);
                if ($file !== null) {
                    $path = $file->store("pumk-bri/realizations/{$period->id}", 'local');
                    if (! is_string($path) || $path === '') {
                        throw new RuntimeException('Dokumen realisasi PUMK BRI gagal disimpan.');
                    }
                }

                return $period->realisasi()->create([
                    'uraian' => $data['uraian'],
                    'waktu' => $data['waktu'],
                    'pic' => $data['pic'],
                    'nominal' => $amount,
                    'dokumen_path' => $path,
                    'dokumen_nama' => $file?->getClientOriginalName(),
                ]);
            });
        } catch (Throwable $exception) {
            if ($path !== null) {
                Storage::disk('local')->delete($path);
            }

            throw $exception;
        }

        $activity->record(
            'create_bri_realization_detail',
            'pumk_bri',
            'Menambahkan rincian realisasi PUMK BRI.',
            $realization,
            metadata: ['periode_tahun' => $realization->periode->periode_tahun],
        );

        return redirect()->route('pumk-admin.umkm-binaan-bri.pumk-bri.index')
            ->with('success', 'Realisasi PUMK BRI berhasil ditambahkan.');
    }

    public function storeAnnualFundSummary(Request $request, PumkActivityLogger $activity): RedirectResponse
    {
        $data = $request->validate([
            'tahun' => ['required', 'integer', 'digits:4', 'between:1900,2100'],
            'total_dana_ditransfer' => ['required', 'string', 'max:80'],
            'angsuran_pokok' => ['required', 'string', 'max:80'],
            'jasmin' => ['required', 'string', 'max:80'],
            'penyaluran' => ['required', 'string', 'max:80'],
            'os' => ['required', 'string', 'max:80'],
        ]);

        $amounts = [];
        foreach (['total_dana_ditransfer', 'angsuran_pokok', 'jasmin', 'penyaluran', 'os'] as $field) {
            $amounts[$field] = $this->rupiah($data[$field], $field);
        }

        $availableFunds = bcadd(
            bcadd($amounts['total_dana_ditransfer'], $amounts['angsuran_pokok'], 0),
            $amounts['jasmin'],
            0,
        );
        $usedFunds = bcadd($amounts['penyaluran'], $amounts['os'], 0);
        $amounts['sisa_dana_akhir'] = bcsub($availableFunds, $usedFunds, 0);
        $year = (int) $data['tahun'];

        $summary = DB::transaction(fn (): PumkBriAnnualFundSummary => PumkBriAnnualFundSummary::query()->updateOrCreate(
            ['tahun' => $year],
            $amounts,
        ));

        $activity->record(
            $summary->wasRecentlyCreated ? 'create_bri_annual_fund_summary' : 'update_bri_annual_fund_summary',
            'pumk_bri',
            'Menyimpan perhitungan dana tahunan PUMK BRI.',
            $summary,
            metadata: ['tahun' => $year],
        );

        return redirect()->route('pumk-admin.umkm-binaan-bri.pumk-bri.index')
            ->with('success', "Perhitungan dana tahunan {$year} berhasil disimpan.");
    }

    public function viewDocument(PumkBriRealization $realization): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless(filled($realization->dokumen_path) && $disk->exists($realization->dokumen_path), 404);
        $mime = $disk->mimeType($realization->dokumen_path) ?: 'application/octet-stream';
        $disposition = in_array($mime, self::INLINE_MIME_TYPES, true) ? 'inline' : 'attachment';

        return $disk->response($realization->dokumen_path, $realization->dokumen_nama, [
            'Content-Type' => $mime,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ], $disposition);
    }

    public function downloadDocument(PumkBriRealization $realization): StreamedResponse
    {
        $disk = Storage::disk('local');
        abort_unless(filled($realization->dokumen_path) && $disk->exists($realization->dokumen_path), 404);

        return $disk->download($realization->dokumen_path, $realization->dokumen_nama, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    private function rupiah(string $value, string $field): string
    {
        if (! preg_match('/^\s*(?:Rp\.?\s*)?[\d.,\s]+\s*$/iu', $value)) {
            throw ValidationException::withMessages([$field => 'Masukkan nominal Rupiah yang valid.']);
        }

        $digits = ltrim(preg_replace('/\D/', '', $value) ?? '', '0');
        if ($digits === '') {
            return '0';
        }
        if (strlen($digits) > 20) {
            throw ValidationException::withMessages([$field => 'Nominal maksimal 20 digit.']);
        }

        return $digits;
    }
}
