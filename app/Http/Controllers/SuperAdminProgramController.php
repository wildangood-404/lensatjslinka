<?php

namespace App\Http\Controllers;

use App\Models\AdminNotification;
use App\Models\Pillar;
use App\Models\Program;
use App\Models\ProgramDocument;
use App\Models\ProgramLog;
use App\Models\StatusLog;
use App\Services\ProgramDocumentResponses;
use App\Support\TjslSubmissionStatusFilter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class SuperAdminProgramController extends Controller
{
    public function index(Request $request): View
    {
        $statusFilter = TjslSubmissionStatusFilter::selectedProgram($request, false);
        $selectedPillar = $request->filled('pillar')
            ? Pillar::where('slug', $request->query('pillar'))->first()
            : null;

        $programsQuery = Program::with('pillar', 'creator')
            ->where('is_archived', false)
            ->when($selectedPillar, fn ($query) => $query->where('pillar_id', $selectedPillar->id));
        $programs = TjslSubmissionStatusFilter::apply($programsQuery, $statusFilter, false)->latest()->get();

        return view('superadmin.programs.index', [
            'programs' => $programs,
            'selectedPillar' => $selectedPillar,
            'statusFilter' => $statusFilter,
            'statusOptions' => TjslSubmissionStatusFilter::programOptions(false),
        ]);
    }

    public function show(Program $program): View
    {
        return view('admin.programs.form', [
            'program' => $program->load('pillar', 'documents', 'photos', 'tujuan', 'assistanceItems', 'verificationVendors', 'creator', 'logs.changer'),
            'pillars' => Pillar::all(),
            'editable' => false,
            'routePrefix' => 'superadmin',
            'superAdminView' => true,
        ]);
    }

    public function reviewTahap1(Request $request, Program $program): RedirectResponse
    {
        abort_unless($program->documents()->where('kategori_tahap', 'proposal')
            ->where('document_type', 'proposal_pengajuan_program')->exists(), 422);

        return $this->review($request, $program, 'menunggu_review_proposal', [
            'approve' => 'proposal_approve',
            'revise' => 'revisi',
            'reject' => 'ditolak',
        ], 'Review Pengajuan Proposal Program');
    }

    public function reviewTahap2(Request $request, Program $program): RedirectResponse
    {
        abort_unless($program->documents()->where('kategori_tahap', 'survei')
            ->where('document_type', 'kelengkapan_survei')->exists(), 422);

        return $this->review($request, $program, 'menunggu_review_survei', [
            'approve' => 'survei_disetujui',
            'revise' => 'perbaikan',
            'reject' => 'ditolak',
        ], 'Review Verifikasi Program');
    }

    public function reviewLaporanAkhir(Request $request, Program $program): RedirectResponse
    {
        abort_unless(
            $program->documents()->where('kategori_tahap', 'laporan_akhir')->where('document_type', 'laporan_akhir')->exists()
            && $program->documents()->where('kategori_tahap', 'dokumentasi')->where('document_type', 'dokumentasi_kegiatan')->exists(),
            422,
        );

        return $this->review($request, $program, 'menunggu_review_laporan_akhir', [
            'approve' => 'selesai',
            'revise' => 'revisi_laporan_akhir',
        ], 'Review Laporan Akhir');
    }

    private function review(Request $request, Program $program, string $expectedStatus, array $transitions, string $label): RedirectResponse
    {
        abort_unless($program->status === $expectedStatus && ! $program->is_archived, 422);
        $data = $request->validate([
            'decision' => ['required', 'in:'.implode(',', array_keys($transitions))],
            'catatan' => ['required_if:decision,revise,reject', 'nullable', 'string', 'min:5'],
        ]);

        $from = $program->status;
        $to = $transitions[$data['decision']];
        $note = $data['catatan'] ?? null;
        DB::transaction(function () use ($request, $program, $from, $to, $note, $label): void {
            $program->update([
                'status' => $to,
                'catatan_terakhir' => $note,
                'rejected_reason' => $to === 'ditolak' ? $note : null,
                'fase1_reviewed_by' => $label === 'Review Pengajuan Proposal Program' ? $request->user()->id : $program->fase1_reviewed_by,
                'fase1_reviewed_at' => $label === 'Review Pengajuan Proposal Program' ? now() : $program->fase1_reviewed_at,
                'fase1_rejected_reason' => $label === 'Review Pengajuan Proposal Program' && $to === 'ditolak' ? $note : null,
                'fase2_reviewed_by' => $label === 'Review Verifikasi Program' ? $request->user()->id : $program->fase2_reviewed_by,
                'fase2_reviewed_at' => $label === 'Review Verifikasi Program' ? now() : $program->fase2_reviewed_at,
                'fase2_rejected_reason' => $label === 'Review Verifikasi Program' && $to === 'ditolak' ? $note : null,
            ]);
            $this->log($program, $from, $to, $request->user()->id, $note);
        });
        $this->notifyCreator(
            $program,
            "{$label} Program TJSL",
            "Status program \"{$program->nama_program}\" berubah menjadi {$to}.".($note ? " Catatan: {$note}" : ''),
        );

        return back()->with('success', "{$label} berhasil: ".str_replace('_', ' ', ucfirst($to)).'.');
    }

    public function approve(Request $request, Program $program): RedirectResponse
    {
        abort_unless(in_array($program->status, ['pending_fase1', 'pending_fase2'], true), 422);

        $from = $program->status;

        if ($from === 'pending_fase1') {
            $this->ensurePhaseOneDocuments($program);

            $program->update([
                'status' => 'approved_fase1',
                'fase1_reviewed_by' => $request->user()->id,
                'fase1_reviewed_at' => now(),
                'fase1_rejected_reason' => null,
            ]);

            $message = 'Program "'.$program->nama_program.'" telah disetujui. Admin dapat mengunggah dokumen BAST.';
            $success = 'Program disetujui.';
        } else {
            abort_unless($program->documents()->where('document_type', 'bast')->exists(), 422);

            $program->update([
                'status' => 'completed',
                'fase2_reviewed_by' => $request->user()->id,
                'fase2_reviewed_at' => now(),
                'fase2_rejected_reason' => null,
            ]);

            $message = 'Dokumen BAST program "'.$program->nama_program.'" telah disetujui. Program dinyatakan selesai.';
            $success = 'Fase 2 disetujui dan program telah selesai.';
        }

        $this->log($program, $from, $program->status, $request->user()->id);
        $this->notifyCreator($program, 'Review Program TJSL Diterima', $message);

        $response = back()->with('success', $success);

        if ($from === 'pending_fase2') {
            $response->with('approval_completed', [
                'type' => 'internal',
                'name' => $program->nama_program,
            ]);
        }

        return $response;
    }

    public function reject(Request $request, Program $program): RedirectResponse
    {
        $data = $request->validate([
            'rejected_reason' => ['required', 'string', 'min:5'],
        ]);

        abort_unless(in_array($program->status, ['pending_fase1', 'pending_fase2'], true), 422);

        $from = $program->status;

        if ($from === 'pending_fase1') {
            $program->update([
                'status' => 'rejected_fase1',
                'fase1_reviewed_by' => $request->user()->id,
                'fase1_reviewed_at' => now(),
                'fase1_rejected_reason' => $data['rejected_reason'],
            ]);

            $title = 'Pengajuan Program TJSL Ditolak';
            $message = 'Program "'.$program->nama_program.'" ditolak: '.$data['rejected_reason'];
            $success = 'Program ditolak dan tetap tercatat pada Overview Program TJSL.';
        } else {
            $program->update([
                'status' => 'approved_fase1',
                'fase2_reviewed_by' => $request->user()->id,
                'fase2_reviewed_at' => now(),
                'fase2_rejected_reason' => $data['rejected_reason'],
            ]);

            $title = 'Dokumen BAST Program TJSL Ditolak';
            $message = 'Dokumen BAST program "'.$program->nama_program.'" ditolak: '.$data['rejected_reason'].'. Silakan unggah ulang BAST.';
            $success = 'Dokumen BAST ditolak. Admin dapat mengunggah ulang tanpa mengulang dokumen awal.';
        }

        $this->log($program, $from, $program->status, $request->user()->id, $data['rejected_reason']);
        $this->notifyCreator($program, $title, $message);

        return back()->with('success', $success);
    }

    public function downloadDocument(ProgramDocument $document, ProgramDocumentResponses $responses)
    {
        return $responses->download($document);
    }

    public function viewDocument(ProgramDocument $document, ProgramDocumentResponses $responses)
    {
        return $responses->view($document);
    }

    private function log(
        Program $program,
        ?string $from,
        string $to,
        int $userId,
        ?string $note = null,
    ): void {
        ProgramLog::create([
            'program_id' => $program->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'catatan' => $note,
        ]);

        StatusLog::create([
            'related_type' => 'program',
            'related_id' => $program->id,
            'from_status' => $from,
            'to_status' => $to,
            'changed_by' => $userId,
            'note' => $note,
        ]);
    }

    private function notifyCreator(Program $program, string $title, string $message): void
    {
        if (! $program->created_by) {
            return;
        }

        AdminNotification::create([
            'user_id' => $program->created_by,
            'title' => $title,
            'message' => $message,
            'related_type' => 'program',
            'related_id' => $program->id,
        ]);
    }

    private function ensurePhaseOneDocuments(Program $program): void
    {
        $requiredDocuments = $program->phaseOneDocuments();
        $existingTypes = $program->documents()
            ->whereIn('document_type', array_keys($requiredDocuments))
            ->pluck('document_type');

        $missingDocuments = collect($requiredDocuments)
            ->reject(fn ($label, $type) => $existingTypes->contains($type))
            ->values();

        if ($missingDocuments->isNotEmpty()) {
            throw ValidationException::withMessages([
                'phase_one_documents' => 'Program belum dapat disetujui. Dokumen yang belum lengkap: '
                    .$missingDocuments->join(', ').'.',
            ]);
        }
    }
}
