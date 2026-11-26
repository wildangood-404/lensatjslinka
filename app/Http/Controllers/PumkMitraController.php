<?php

namespace App\Http\Controllers;

use App\Models\PumkAngsuran;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkPinjamanDokumen;
use App\Services\Monitoring\PumkClassificationService;
use App\Services\Pumk\KartuPiutangService;
use App\Services\Pumk\PiutangCalculator;
use App\Services\Pumk\PumkActivityLogger;
use App\Services\Pumk\PumkCardExports;
use App\Services\Pumk\PumkCollectibilitySummaryService;
use App\Services\Pumk\PumkDocumentResponses;
use App\Services\Pumk\PumkInstallmentChanges;
use App\Services\Pumk\PumkLoanDocumentService;
use App\Services\Pumk\PumkLoanSettlementService;
use App\Services\Pumk\PumkLoanSnapshotService;
use App\Services\Pumk\PumkMitraChanges;
use App\Services\Pumk\PumkMitraOwnership;
use App\Services\Pumk\PumkMitraPages;
use App\Services\Pumk\PumkPaymentProofService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PumkMitraController extends Controller
{
    public function index(Request $request, PumkCollectibilitySummaryService $collectibilitySummary): View
    {
        return app(PumkMitraPages::class)->index($request, $collectibilitySummary, $this->sourceCategory($request));
    }

    public function create(Request $request): View
    {
        return app(PumkMitraPages::class)->create($this->sourceCategory($request));
    }

    public function store(
        Request $request,
        PiutangCalculator $calculator,
        PumkActivityLogger $activity,
        PumkLoanDocumentService $documents,
        PumkClassificationService $classifications,
        PumkLoanSnapshotService $snapshots,
        PumkLoanSettlementService $settlement,
    ): RedirectResponse {
        $sourceCategory = $this->sourceCategory($request);

        return app(PumkMitraChanges::class)->store($request, $calculator, $activity, $documents, $classifications, $snapshots, $settlement, $sourceCategory);
    }

    public function show(Request $request, PumkMitra $mitra, KartuPiutangService $kartuPiutang, PumkLoanSettlementService $settlement): View
    {
        return app(PumkMitraPages::class)->show($request, $mitra, $kartuPiutang, $settlement, $this->sourceCategory($request));
    }

    public function edit(Request $request, PumkMitra $mitra): View
    {
        return app(PumkMitraPages::class)->edit($request, $mitra, $this->sourceCategory($request));
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
    ): RedirectResponse {
        $sourceCategory = $this->sourceCategory($request);

        return app(PumkMitraChanges::class)->update($request, $mitra, $calculator, $activity, $documents, $classifications, $snapshots, $settlement, $sourceCategory);
    }

    public function reopen(Request $request, PumkMitra $mitra, PumkPinjaman $pinjaman, PumkLoanSettlementService $settlement): RedirectResponse
    {
        $this->assertMitraCategory($request, $mitra);
        PumkMitraOwnership::loan($mitra, $pinjaman);
        $data = $request->validate(['reopen_note' => ['required', 'string', 'min:5', 'max:1000']]);
        $settlement->reopen($mitra->id, $pinjaman->id, $data['reopen_note'], (int) auth('pumk')->id());

        return redirect()->route($this->cardRoute($request), [$mitra, 'pinjaman' => $pinjaman->id])
            ->with('success', 'Pinjaman lama dibuka kembali dengan saldo dan histori yang sama. Tidak ada pinjaman baru.');
    }

    public function storeAngsuran(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PiutangCalculator $calculator,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        $sourceCategory = $this->sourceCategory($request);
        $this->assertMitraCategory($request, $mitra);

        return app(PumkInstallmentChanges::class)->storeAngsuran($request, $mitra, $pinjaman, $calculator, $proofs, $sourceCategory);
    }

    public function updateAngsuran(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
        PiutangCalculator $calculator,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        $sourceCategory = $this->sourceCategory($request);
        $this->assertMitraCategory($request, $mitra);

        return app(PumkInstallmentChanges::class)->updateAngsuran($request, $mitra, $pinjaman, $angsuran, $calculator, $proofs, $sourceCategory);
    }

    public function markPaid(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkLoanSettlementService $settlement,
    ): RedirectResponse {
        $this->assertMitraCategory($request, $mitra);
        PumkMitraOwnership::loan($mitra, $pinjaman);
        $data = $request->validate([
            'lunas_note' => ['nullable', 'string', 'max:1000'],
        ]);
        $result = $settlement->settle(
            $mitra->id, $pinjaman->id, $data['lunas_note'] ?? null,
            (int) auth('pumk')->id(),
        );

        return redirect()->route($this->cardRoute($request), [$mitra, 'pinjaman' => $pinjaman->id])
            ->with('success', $result['status'] === 'already_paid'
                ? 'Pinjaman sudah berstatus lunas; catatan penutupan pertama tetap tersimpan.'
                : 'Pinjaman berhasil ditandai lunas. Seluruh histori tetap tersimpan.');
    }

    public function exportExcel(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        KartuPiutangService $kartuPiutang,
    ): Response {
        $this->assertMitraCategory($request, $mitra);

        return app(PumkCardExports::class)->exportExcel($request, $mitra, $pinjaman, $kartuPiutang);
    }

    public function exportPdf(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        KartuPiutangService $kartuPiutang,
    ): Response {
        $this->assertMitraCategory($request, $mitra);

        return app(PumkCardExports::class)->exportPdf($request, $mitra, $pinjaman, $kartuPiutang);
    }

    public function viewDocument(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): StreamedResponse {
        $this->assertMitraCategory($request, $mitra);

        return app(PumkDocumentResponses::class)->viewDocument($mitra, $pinjaman, $document);
    }

    public function downloadDocument(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
    ): StreamedResponse {
        $this->assertMitraCategory($request, $mitra);

        return app(PumkDocumentResponses::class)->downloadDocument($mitra, $pinjaman, $document);
    }

    public function destroyDocument(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkPinjamanDokumen $document,
        PumkLoanDocumentService $documents,
    ): RedirectResponse {
        $this->assertMitraCategory($request, $mitra);

        return app(PumkDocumentResponses::class)->destroyDocument($mitra, $pinjaman, $document, $documents);
    }

    public function viewPaymentProof(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
    ): StreamedResponse {
        $this->assertMitraCategory($request, $mitra);

        return app(PumkDocumentResponses::class)->viewPaymentProof($mitra, $pinjaman, $angsuran);
    }

    public function downloadPaymentProof(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
    ): StreamedResponse {
        $this->assertMitraCategory($request, $mitra);

        return app(PumkDocumentResponses::class)->downloadPaymentProof($mitra, $pinjaman, $angsuran);
    }

    public function destroyPaymentProof(
        Request $request,
        PumkMitra $mitra,
        PumkPinjaman $pinjaman,
        PumkAngsuran $angsuran,
        PumkPaymentProofService $proofs,
    ): RedirectResponse {
        $this->assertMitraCategory($request, $mitra);

        return app(PumkDocumentResponses::class)->destroyPaymentProof($mitra, $pinjaman, $angsuran, $proofs);
    }

    private function sourceCategory(Request $request): string
    {
        return $request->routeIs('pumk-admin.umkm-binaan-bri.*') ? 'bri' : 'reguler';
    }

    private function assertMitraCategory(Request $request, PumkMitra $mitra): void
    {
        if ($request->routeIs('superadmin.*')) {
            return;
        }

        abort_unless($mitra->kategori_binaan === $this->sourceCategory($request), 404);
    }

    private function cardRoute(Request $request): string
    {
        return $this->sourceCategory($request) === 'bri'
            ? 'pumk-admin.umkm-binaan-bri.card'
            : 'pumk-admin.mitra.show';
    }
}
