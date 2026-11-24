<?php

namespace App\Services\AdminTjsl;

use App\Models\Pillar;
use App\Models\Program;
use App\Services\SubmissionNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

final class ProgramChanges
{
    public function __construct(
        private readonly ProgramWorkflow $workflow,
        private readonly ProgramDocuments $documents,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $legacySubmission = $this->isLegacySubmission($request);
        $submit = $request->input('action') === 'submit';
        $data = $this->validateData($request, $legacySubmission, $submit);
        foreach (['sasaran_program', 'lokasi_program', 'tujuan_program'] as $field) {
            $data[$field] = $data[$field] ?? '';
        }
        $assistanceItems = $data['assistance_items'] ?? [];
        unset($data['assistance_items']);

        $program = DB::transaction(function () use ($request, $data, $assistanceItems, $submit, $legacySubmission) {
            $program = Program::create([
                ...$data,
                'slug' => $this->slug($data['nama_program']),
                'created_by' => $request->user()->id,
                'status' => 'draft',
                'submitted_at' => null,
            ]);
            $this->syncAssistanceItems($program, $assistanceItems);

            if ($legacySubmission) {
                $this->documents->syncFiles($request, $program);
            } else {
                $this->storeStageDocuments($request, $program, 'proposal', 'documents');
            }
            $legacyErrors = $legacySubmission && $submit ? $this->documents->phaseOneDocumentErrors($program) : [];
            if ($submit && $legacySubmission && $legacyErrors === []) {
                $program->update(['status' => 'pending_fase1', 'submitted_at' => now()]);
            } elseif ($submit && ! $legacySubmission && $program->documents()->where('kategori_tahap', 'proposal')
                ->where('document_type', 'proposal_pengajuan_program')->exists()) {
                $program->update([
                    'status' => 'menunggu_review_proposal',
                    'submitted_at' => now(),
                ]);
            }

            $this->workflow->log($program, null, $program->status, $request->user()->id);

            return $program;
        });

        $documentErrors = $legacySubmission && $submit
            ? $this->documents->phaseOneDocumentErrors($program)
            : ($submit && $program->status === 'draft'
                ? ['documents.proposal_pengajuan_program.file' => 'Proposal Program wajib diunggah sebelum mengajukan.']
                : []);

        if ($documentErrors !== []) {
            return redirect()
                ->route('admin.programs.show', $program)
                ->withErrors($documentErrors)
                ->with('success', 'Program disimpan sebagai draft. Unggah Proposal Program untuk mengajukan.');
        }

        if ($program->status === 'pending_fase1') {
            app(SubmissionNotificationService::class)->programSubmitted($program, 1);
        } elseif ($program->status === 'menunggu_review_proposal') {
            app(SubmissionNotificationService::class)->programWorkflowSubmitted($program, 'proposal');
        }

        return redirect()
            ->route('admin.programs.show', $program)
            ->with('success', 'Program berhasil disimpan.');
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        $this->workflow->own($request, $program);
        abort_unless($program->canBeEdited() && in_array($program->status, ['draft', 'revisi'], true), 403);

        $submit = $request->input('action') === 'submit';
        $request->mergeIfMissing([
            'sasaran_program' => $program->sasaran_program,
            'lokasi_program' => $program->lokasi_program,
            'tujuan_program' => $program->tujuan_program,
        ]);
        $data = $this->validateData($request, false, $submit);
        $assistanceItems = $data['assistance_items'] ?? [];
        unset($data['assistance_items']);
        $hasProposal = $program->documents()
            ->where('kategori_tahap', 'proposal')
            ->where('document_type', 'proposal_pengajuan_program')
            ->exists();
        $hasNewProposal = $request->hasFile('documents.proposal_pengajuan_program.file');
        if ($submit && ! $hasProposal && ! $hasNewProposal) {
            return back()->withErrors([
                'documents.proposal_pengajuan_program.file' => 'Proposal Program wajib tersedia untuk mengajukan.',
            ])->withInput();
        }

        DB::transaction(function () use ($request, $program, $data, $assistanceItems, $submit) {
            $from = $program->status;

            $program->update([
                ...$data,
                'slug' => $this->slug($data['nama_program'], $program->id),
                'status' => $submit ? 'menunggu_review_proposal' : 'draft',
                'submitted_at' => $submit ? now() : null,
                'fase1_rejected_reason' => null,
                'catatan_terakhir' => null,
            ]);
            $this->syncAssistanceItems($program, $assistanceItems);

            $this->storeStageDocuments($request, $program, 'proposal', 'documents');

            if ($from !== $program->status) {
                $this->workflow->log($program, $from, $program->status, $request->user()->id);
            }
        });

        if ($program->status === 'menunggu_review_proposal') {
            app(SubmissionNotificationService::class)->programWorkflowSubmitted($program, 'proposal');
        }

        return back()->with('success', 'Perubahan program tersimpan.');
    }

    public function cancel(Request $request, Program $program): RedirectResponse
    {
        $this->workflow->own($request, $program);
        abort_unless(in_array($program->status, ['menunggu_review_proposal', 'pending_fase1'], true), 403);
        $from = $program->status;

        $program->update([
            'status' => 'draft',
            'submitted_at' => null,
        ]);

        $this->workflow->log(
            $program,
            $from,
            'draft',
            $request->user()->id,
            'Pengajuan dibatalkan Admin',
        );

        return redirect()->route('admin.programs.show', $program);
    }

    public function validateData(Request $request, bool $legacySubmission = false, bool $strictProposal = true): array
    {
        $request->mergeIfMissing(['jenis_kerjasama' => 'pks']);
        $request->mergeIfMissing([
            'jenis_pengajuan' => 'proposal_tjsl',
            'tipe_kerjasama' => $request->input('jenis_kerjasama') === 'non_pks' ? 'NON PKS' : 'PKS',
        ]);
        if (! $legacySubmission && ! $request->has('jenis_bantuan') && $request->filled('rencana_anggaran')) {
            $request->merge([
                'jenis_bantuan' => ['uang_tunai'],
                'nominal_uang_tunai' => $request->input('rencana_anggaran'),
            ]);
        }
        foreach (['rencana_anggaran', 'nominal_uang_tunai', 'realisasi_anggaran'] as $field) {
            if ($request->has($field) && is_string($request->input($field))) {
                $request->merge([$field => preg_replace('/\D/', '', $request->input($field))]);
            }
        }

        $selectedAssistance = $request->input('jenis_bantuan', []);
        $requiresBarang = is_array($selectedAssistance) && in_array('barang', $selectedAssistance, true);
        $requiresJasa = is_array($selectedAssistance) && in_array('jasa', $selectedAssistance, true);
        $rules = [
            'pillar_id' => ['required', 'exists:pillars,id'],
            'jenis_kerjasama' => ['required', Rule::in(Program::COOPERATION_TYPES)],
            'tipe_kerjasama' => ['required', Rule::in(['PKS', 'NON PKS'])],
            'jenis_pengajuan' => ['required', Rule::in(['proposal_tjsl', 'external_stakeholder'])],
            'nama_mitra' => [Rule::requiredIf($strictProposal && $request->input('jenis_pengajuan') === 'external_stakeholder'), 'nullable', 'string', 'max:255'],
            'no_surat' => [Rule::requiredIf($strictProposal && $request->input('jenis_pengajuan') === 'external_stakeholder'), 'nullable', 'string', 'max:255'],
            'tanggal_surat' => [Rule::requiredIf($strictProposal && $request->input('jenis_pengajuan') === 'external_stakeholder'), 'nullable', 'date'],
            'kategori_program' => ['nullable', 'string', 'max:255'],
            'nama_program' => ['required', 'string', 'max:255'],
            'deskripsi_program' => ['required', 'string'],
            'tanggal_program' => [Rule::requiredIf($strictProposal), 'nullable', 'date'],
            'sasaran_program' => ['nullable', 'string'],
            'lokasi_program' => ['nullable', 'string'],
            'mitra_program' => ['nullable', 'string'],
            'jenis_bantuan' => [Rule::requiredIf($strictProposal), 'nullable', 'array', 'min:1'],
            'jenis_bantuan.*' => ['required', Rule::in(['uang_tunai', 'barang', 'jasa'])],
            'nominal_uang_tunai' => [
                Rule::requiredIf($strictProposal && is_array($selectedAssistance) && in_array('uang_tunai', $selectedAssistance, true)),
                'nullable',
                'numeric',
                'min:0',
            ],
            'assistance_items.barang' => [
                Rule::requiredIf($strictProposal && $requiresBarang),
                'nullable',
                'array',
                'min:1',
            ],
            'assistance_items.barang.*.nama_item' => [Rule::requiredIf($strictProposal && $requiresBarang), 'nullable', 'string', 'max:255'],
            'assistance_items.barang.*.jumlah' => ['nullable', 'string', 'max:100'],
            'assistance_items.barang.*.keterangan' => ['nullable', 'string', 'max:255'],
            'assistance_items.jasa' => [
                Rule::requiredIf($strictProposal && $requiresJasa),
                'nullable',
                'array',
                'min:1',
            ],
            'assistance_items.jasa.*.nama_item' => [Rule::requiredIf($strictProposal && $requiresJasa), 'nullable', 'string', 'max:255'],
            'assistance_items.jasa.*.jumlah' => ['nullable', 'string', 'max:100'],
            'assistance_items.jasa.*.keterangan' => ['nullable', 'string', 'max:255'],
            'pic_nama' => ['nullable', 'string', 'max:255'],
            'pic_telepon' => ['nullable', 'string', 'max:50'],
            'realisasi_anggaran' => ['nullable', 'numeric', 'min:0'],
            'tujuan_program' => ['nullable', 'string', 'max:5000'],
            'documents.*.nama' => ['nullable', 'string', 'max:255'],
            'documents.*.file' => ['nullable', 'file', 'max:10240'],
        ];
        if ($legacySubmission) {
            $rules['sasaran_program'] = ['required', 'string'];
            $rules['lokasi_program'] = ['required', 'string'];
            $rules['rencana_anggaran'] = ['required', 'numeric', 'min:0'];
            unset(
                $rules['tanggal_program'],
                $rules['jenis_bantuan'],
                $rules['jenis_bantuan.*'],
                $rules['nominal_uang_tunai'],
                $rules['assistance_items.barang'],
                $rules['assistance_items.barang.*.nama_item'],
                $rules['assistance_items.barang.*.jumlah'],
                $rules['assistance_items.barang.*.keterangan'],
                $rules['assistance_items.jasa'],
                $rules['assistance_items.jasa.*.nama_item'],
                $rules['assistance_items.jasa.*.jumlah'],
                $rules['assistance_items.jasa.*.keterangan'],
                $rules['pic_nama'],
                $rules['pic_telepon'],
                $rules['no_surat'],
                $rules['tanggal_surat'],
            );
        }

        $data = $request->validate($rules);

        $selectedPillar = Pillar::find($data['pillar_id']);
        $data['jenis_kerjasama'] = $data['tipe_kerjasama'] === 'NON PKS' ? 'non_pks' : 'pks';
        $data['kategori_program'] = $selectedPillar?->name;
        $data['mitra_program'] = $data['nama_mitra'] ?? $data['mitra_program'] ?? 'Internal TJSL INKA';
        if ($data['jenis_pengajuan'] !== 'external_stakeholder') {
            $data['nama_mitra'] = null;
            $data['no_surat'] = null;
            $data['tanggal_surat'] = null;
            $data['mitra_program'] = 'Internal TJSL INKA';
        }
        if ($legacySubmission) {
            $data['jenis_bantuan'] = ['uang_tunai'];
            $data['nominal_uang_tunai'] = $data['rencana_anggaran'];
            $data['tanggal_program'] = null;
            $data['pic_nama'] = null;
            $data['pic_telepon'] = null;
        } else {
            $data['jenis_bantuan'] = $data['jenis_bantuan'] ?? [];
            $data['rencana_anggaran'] = in_array('uang_tunai', $data['jenis_bantuan'], true)
                ? ($data['nominal_uang_tunai'] ?? null)
                : null;
            $jasa = collect($data['assistance_items']['jasa'] ?? [])
                ->filter(fn (array $item) => filled($item['nama_item'] ?? null));
            $data['detail_jasa'] = $jasa->isEmpty()
                ? null
                : $jasa
                    ->map(fn (array $item) => trim($item['nama_item'].(filled($item['keterangan'] ?? null) ? ': '.$item['keterangan'] : '')))
                    ->implode("\n");
        }

        return $data;
    }

    private function syncAssistanceItems(Program $program, array $items): void
    {
        $program->assistanceItems()->delete();

        foreach (['barang', 'jasa'] as $type) {
            foreach ($items[$type] ?? [] as $item) {
                if (! filled($item['nama_item'] ?? null)) {
                    continue;
                }

                $program->assistanceItems()->create([
                    'tipe' => $type,
                    'nama_item' => $item['nama_item'],
                    'jumlah' => $item['jumlah'] ?? null,
                    'keterangan' => $item['keterangan'] ?? null,
                ]);
            }
        }
    }

    public function storeStageDocuments(Request $request, Program $program, string $stage, string $field): void
    {
        foreach ($request->file($field, []) as $key => $payload) {
            if (! isset($payload['file'])) {
                continue;
            }

            $file = $payload['file'];
            $primaryType = [
                'proposal' => 'proposal_pengajuan_program',
                'survei' => 'kelengkapan_survei',
                'laporan_akhir' => 'laporan_akhir',
                'dokumentasi' => 'dokumentasi_kegiatan',
            ][$stage] ?? '';
            $type = $key === $primaryType ? $primaryType : 'lainnya';
            $path = $file->store("programs/{$program->id}/documents", 'local');
            $document = $program->documents()->create([
                'document_type' => $type,
                'kategori_tahap' => $stage,
                'nama_dokumen' => $request->input("{$field}.{$key}.nama") ?: $file->getClientOriginalName(),
                'nama_file_asli' => $file->getClientOriginalName(),
                'file_path' => $path,
                'uploaded_at' => now(),
            ]);
            if ($type === $primaryType) {
                $oldDocuments = $program->documents()
                    ->where('kategori_tahap', $stage)
                    ->where('document_type', $type)
                    ->whereKeyNot($document->id)
                    ->get();
                $oldDocuments->each->delete();
                $oldDocuments->each(fn ($oldDocument) => Storage::disk('local')->delete($oldDocument->file_path));
            }
        }
    }

    public function slug(string $name, ?int $ignore = null): string
    {
        $base = Str::slug($name);
        $slug = $base;
        $index = 2;

        while (Program::where('slug', $slug)->when($ignore, fn ($query) => $query->whereKeyNot($ignore))->exists()) {
            $slug = "{$base}-".$index++;
        }

        return $slug;
    }

    private function isLegacySubmission(Request $request): bool
    {
        return collect(array_keys($request->file('documents', [])))
            ->intersect(array_keys(Program::PHASE_ONE_DOCUMENTS))
            ->contains(fn (string $type): bool => $type !== 'proposal_pengajuan_program');
    }
}
