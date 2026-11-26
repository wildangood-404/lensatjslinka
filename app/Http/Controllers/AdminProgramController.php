<?php

namespace App\Http\Controllers;

use App\Models\Pillar;
use App\Models\Program;
use App\Models\ProgramDocument;
use App\Services\AdminTjsl\ProgramChanges;
use App\Services\AdminTjsl\ProgramDocuments;
use App\Services\AdminTjsl\ProgramPages;
use App\Services\AdminTjsl\ProgramWorkflow;
use App\Services\SubmissionNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AdminProgramController extends Controller
{
    public function __construct(
        private readonly ProgramPages $pages,
        private readonly ProgramChanges $changes,
        private readonly ProgramDocuments $documents,
    ) {}

    public function index(Request $request): View
    {
        return $this->pages->index($request);
    }

    public function create(): View
    {
        return $this->pages->create();
    }

    public function createCooperationForm(string $jenisKerjasama): View
    {
        return $this->pages->createCooperationForm($jenisKerjasama);
    }

    public function store(Request $request): RedirectResponse
    {
        return $this->changes->store($request);
    }

    public function show(Request $request, Program $program): View
    {
        return $this->pages->show($request, $program);
    }

    public function phaseTwo(Request $request, Program $program): View
    {
        return $this->pages->phaseTwo($request, $program);
    }

    public function update(Request $request, Program $program): RedirectResponse
    {
        return $this->changes->update($request, $program);
    }

    public function cancel(Request $request, Program $program): RedirectResponse
    {
        return $this->changes->cancel($request, $program);
    }

    public function uploadBast(Request $request, Program $program): RedirectResponse
    {
        return $this->documents->uploadBast($request, $program);
    }

    public function storeTahap2(Request $request, Program $program): RedirectResponse
    {
        $this->pages->authorizeOwner($request, $program);
        abort_unless(in_array($program->status, ['proposal_approve', 'perbaikan'], true), 403);

        if (is_string($request->input('sisa_anggaran'))) {
            $request->merge(['sisa_anggaran' => preg_replace('/\D/', '', $request->input('sisa_anggaran'))]);
        }
        $vendorsInput = $request->input('vendors');
        $serviceVendorsInput = $request->input('service_vendors');
        foreach (['vendors' => $vendorsInput, 'service_vendors' => $serviceVendorsInput] as $field => $rows) {
            if (! is_array($rows)) {
                continue;
            }

            foreach ($rows as $index => $vendor) {
                if (isset($vendor['harga']) && is_string($vendor['harga'])) {
                    $rows[$index]['harga'] = preg_replace('/\D/', '', $vendor['harga']);
                }
            }
            $request->merge([$field => $rows]);
        }

        $assistanceTypes = $request->input('jenis_bantuan_verifikasi', []);
        $hasMoney = is_array($assistanceTypes) && in_array('uang', $assistanceTypes, true);
        $hasGoods = is_array($assistanceTypes) && in_array('barang', $assistanceTypes, true);
        $hasService = is_array($assistanceTypes) && in_array('jasa', $assistanceTypes, true);
        $isProcurement = $hasGoods && $request->input('jalur_pengadaan_barang') === 'proses_pengadaan';
        $requiresGoodsVendors = $isProcurement && $request->input('sub_jalur_pengadaan') === 'non_logistik';
        $isUsedGoods = $hasGoods && $request->input('jalur_pengadaan_barang') === 'barang_bekas';
        $requiresServiceVendors = $hasService && $request->input('kategori_jasa') === 'non_logistik';
        $data = $request->validate([
            'kesesuaian_rka' => ['required', Rule::in(['CID', 'NON CID'])],
            'pilar_verifikasi' => ['required', 'string', 'exists:pillars,name'],
            'no_tpb' => ['nullable', 'string', 'max:255'],
            'jenis_program_tpb' => ['nullable', 'string', 'max:255'],
            'sisa_anggaran' => ['nullable', 'numeric', 'min:0'],
            'kebutuhan_program' => ['required', 'string'],
            'lokasi_program' => ['required', 'string', 'max:255'],
            'sasaran_program' => ['required', 'string', 'max:255'],
            'tanggal_pelaksanaan' => ['required', 'date'],
            'keterangan_verifikasi' => ['nullable', 'string'],
            'jenis_bantuan_verifikasi' => ['required', 'array', 'min:1'],
            'jenis_bantuan_verifikasi.*' => ['required', Rule::in(['uang', 'barang', 'jasa'])],
            'sumber_pencairan' => [Rule::requiredIf($hasMoney), 'nullable', Rule::in(['kas_bon', 'dana_sharing', 'lainnya'])],
            'sumber_pencairan_lainnya' => [Rule::requiredIf($hasMoney && $request->input('sumber_pencairan') === 'lainnya'), 'nullable', 'string', 'max:255'],
            'nama_pemilik_rekening' => [Rule::requiredIf($hasMoney), 'nullable', 'string', 'max:255'],
            'nomor_rekening' => [Rule::requiredIf($hasMoney), 'nullable', 'string', 'max:100'],
            'nama_bank' => [Rule::requiredIf($hasMoney), 'nullable', 'string', 'max:255'],
            'jalur_pengadaan_barang' => [Rule::requiredIf($hasGoods), 'nullable', Rule::in(['proses_pengadaan', 'barang_bekas'])],
            'sub_jalur_pengadaan' => [Rule::requiredIf($isProcurement), 'nullable', Rule::in(['logistik', 'non_logistik'])],
            'vendors' => [Rule::requiredIf($requiresGoodsVendors), 'nullable', 'array', 'min:1'],
            'vendors.*.nama_vendor' => [Rule::requiredIf($requiresGoodsVendors), 'nullable', 'string', 'max:255'],
            'vendors.*.rincian_barang' => [Rule::requiredIf($requiresGoodsVendors), 'nullable', 'string', 'max:255'],
            'vendors.*.harga' => [Rule::requiredIf($requiresGoodsVendors), 'nullable', 'numeric', 'min:0'],
            'sumber_barang' => [Rule::requiredIf($isUsedGoods), 'nullable', 'string', 'max:255'],
            'deskripsi_barang' => [Rule::requiredIf($isUsedGoods), 'nullable', 'string'],
            'kategori_jasa' => [Rule::requiredIf($hasService), 'nullable', Rule::in(['logistik', 'non_logistik', 'lainnya'])],
            'keterangan_jasa_verifikasi' => [Rule::requiredIf($hasService), 'nullable', 'string', 'max:255'],
            'service_vendors' => [Rule::requiredIf($requiresServiceVendors), 'nullable', 'array', 'min:1'],
            'service_vendors.*.nama_vendor' => [Rule::requiredIf($requiresServiceVendors), 'nullable', 'string', 'max:255'],
            'service_vendors.*.rincian_jasa' => [Rule::requiredIf($requiresServiceVendors), 'nullable', 'string', 'max:255'],
            'service_vendors.*.harga' => [Rule::requiredIf($requiresServiceVendors), 'nullable', 'numeric', 'min:0'],
            'survey_documents.*.nama' => ['nullable', 'string', 'max:255'],
            'survey_documents.*.file' => ['nullable', 'file', 'max:10240'],
            'phase2_documentation' => ['nullable', 'array', 'max:30'],
            'phase2_documentation.*' => ['file', 'max:10240'],
        ]);
        $hasSurvey = $program->documents()->where('kategori_tahap', 'survei')
            ->where('document_type', 'kelengkapan_survei')->exists();
        if (! $hasSurvey && ! $request->hasFile('survey_documents.kelengkapan_survei.file')) {
            return back()->withErrors([
                'survey_documents.kelengkapan_survei.file' => 'Dokumen Verifikasi Program wajib diunggah.',
            ])->withInput();
        }

        $from = $program->status;
        $vendors = $requiresGoodsVendors
            ? collect($data['vendors'] ?? [])->map(fn (array $vendor) => [
                ...$vendor,
                'tipe' => 'barang',
                'rincian_jasa' => null,
            ])->all()
            : [];
        $serviceVendors = $requiresServiceVendors
            ? collect($data['service_vendors'] ?? [])->map(fn (array $vendor) => [
                'tipe' => 'jasa',
                'nama_vendor' => $vendor['nama_vendor'],
                'rincian_barang' => '',
                'rincian_jasa' => $vendor['rincian_jasa'],
                'harga' => $vendor['harga'],
            ])->all()
            : [];
        unset($data['vendors'], $data['service_vendors'], $data['survey_documents'], $data['phase2_documentation']);
        $selectedPillar = Pillar::where('name', $data['pilar_verifikasi'])->firstOrFail();
        $data['pillar_id'] = $selectedPillar->id;
        if (! $hasMoney) {
            $data = array_merge($data, [
                'sumber_pencairan' => null,
                'sumber_pencairan_lainnya' => null,
                'nama_pemilik_rekening' => null,
                'nomor_rekening' => null,
                'nama_bank' => null,
            ]);
        }
        if (! $hasGoods) {
            $data = array_merge($data, [
                'jalur_pengadaan_barang' => null,
                'sub_jalur_pengadaan' => null,
                'sumber_barang' => null,
                'deskripsi_barang' => null,
            ]);
        } elseif (! $isProcurement) {
            $data['sub_jalur_pengadaan'] = null;
        }
        if ($hasGoods && ! $isUsedGoods) {
            $data['sumber_barang'] = null;
            $data['deskripsi_barang'] = null;
        }
        if (! $hasService) {
            $data['kategori_jasa'] = null;
            $data['keterangan_jasa_verifikasi'] = null;
        }
        if ($hasMoney && $data['sumber_pencairan'] !== 'lainnya') {
            $data['sumber_pencairan_lainnya'] = null;
        }

        DB::transaction(function () use ($request, $program, $from, $data, $vendors, $serviceVendors): void {
            $this->changes->storeStageDocuments($request, $program, 'survei', 'survey_documents');
            foreach ($request->file('phase2_documentation', []) as $file) {
                $program->documents()->create([
                    'document_type' => 'dokumentasi_verifikasi',
                    'kategori_tahap' => 'survei',
                    'nama_dokumen' => $file->getClientOriginalName(),
                    'nama_file_asli' => $file->getClientOriginalName(),
                    'file_path' => $file->store("programs/{$program->id}/documents", 'local'),
                    'uploaded_at' => now(),
                ]);
            }
            $program->update([
                ...$data,
                'status' => 'menunggu_review_survei',
                'catatan_terakhir' => null,
            ]);
            $program->verificationVendors()->delete();
            foreach ([...$vendors, ...$serviceVendors] as $vendor) {
                $program->verificationVendors()->create($vendor);
            }
            app(ProgramWorkflow::class)->log(
                $program,
                $from,
                $program->status,
                $request->user()->id,
            );
        });
        app(SubmissionNotificationService::class)->programWorkflowSubmitted($program, 'survei');

        return back()->with('success', 'Verifikasi Program berhasil diajukan kepada Super Admin.');
    }

    public function updatePengajuan(Request $request, Program $program): RedirectResponse
    {
        $this->pages->authorizeOwner($request, $program);
        abort_unless(
            ! $program->is_archived
            && in_array($program->status, ['survei_disetujui', 'tertunda', 'pelaksanaan'], true),
            403,
        );
        $data = $request->validate([
            'jalur_pengajuan' => ['required', 'array', 'min:1'],
            'jalur_pengajuan.*' => [
                'required',
                Rule::in(['uang_tunai_kas_bon', 'barang_pengadaan_logistik', 'barang_bekas_jasa']),
            ],
        ]);

        $program->update(['jalur_pengajuan' => array_values(array_unique($data['jalur_pengajuan']))]);

        return redirect()
            ->route('admin.programs.show', $program)
            ->withFragment('stage-4')
            ->with('success', 'Tahap Pengajuan tersimpan. Tahap Pelaksanaan Program kini dapat diisi.');
    }

    public function updatePelaksanaan(Request $request, Program $program): RedirectResponse
    {
        $this->pages->authorizeOwner($request, $program);
        abort_unless(
            ! $program->is_archived
            && in_array($program->status, ['survei_disetujui', 'tertunda', 'pelaksanaan'], true),
            403,
        );
        abort_unless(is_array($program->jalur_pengajuan) && $program->jalur_pengajuan !== [], 403);

        $data = $request->validate([
            'status_operasional' => ['required', Rule::in(['tertunda', 'pelaksanaan'])],
            'catatan_operasional' => ['nullable', 'string', 'max:5000'],
        ]);
        $from = $program->status;
        $to = $data['status_operasional'];
        $note = $to === 'tertunda' ? ($data['catatan_operasional'] ?? null) : null;

        DB::transaction(function () use ($request, $program, $from, $to, $note): void {
            $program->update([
                'status' => $to,
                'status_operasional' => $to,
                'catatan_operasional' => $note,
            ]);
            if ($from !== $to) {
                app(ProgramWorkflow::class)->log(
                    $program,
                    $from,
                    $to,
                    $request->user()->id,
                    $note,
                );
            }
        });

        return redirect()
            ->route('admin.programs.show', $program)
            ->withFragment($to === 'pelaksanaan' ? 'stage-5' : 'stage-4')
            ->with('success', $to === 'pelaksanaan'
                ? 'Status pelaksanaan diperbarui. Tahap Pelaporan Akhir kini dapat diisi.'
                : 'Status pelaksanaan diperbarui menjadi Tertunda.');
    }

    public function updateKasbon(Request $request, Program $program): RedirectResponse
    {
        $this->pages->authorizeOwner($request, $program);
        abort_unless($program->status === 'survei_disetujui', 403);

        $decision = $request->input('kasbon_status');
        if ($decision === 'disetujui' && is_string($request->input('realisasi_anggaran'))) {
            $request->merge([
                'realisasi_anggaran' => preg_replace('/\D/', '', $request->input('realisasi_anggaran')),
            ]);
        }
        $data = $request->validate([
            'kasbon_status' => ['required', 'in:disetujui,ditolak'],
            'realisasi_anggaran' => ['required_if:kasbon_status,disetujui', 'nullable', 'numeric', 'min:0'],
            'catatan_penolakan' => ['required_if:kasbon_status,ditolak', 'nullable', 'string', 'min:5'],
        ]);

        $from = $program->status;
        $to = $data['kasbon_status'] === 'disetujui' ? 'kasbon_disetujui' : 'kasbon_ditolak';
        DB::transaction(function () use ($request, $program, $from, $to, $data): void {
            $program->update([
                'status' => $to,
                'realisasi_anggaran' => $data['realisasi_anggaran'] ?? $program->realisasi_anggaran,
                'catatan_terakhir' => $data['catatan_penolakan'] ?? null,
            ]);
            app(ProgramWorkflow::class)->log(
                $program,
                $from,
                $to,
                $request->user()->id,
                $data['catatan_penolakan'] ?? null,
            );
        });

        return back()->with('success', $to === 'kasbon_disetujui'
            ? 'Kasbon disetujui. Form Laporan Akhir kini dapat diisi.'
            : 'Kasbon ditolak dan program dinyatakan selesai.');
    }

    public function storeLaporanAkhir(Request $request, Program $program): RedirectResponse
    {
        $this->pages->authorizeOwner($request, $program);
        abort_unless(in_array($program->status, ['pelaksanaan', 'kasbon_disetujui', 'revisi_laporan_akhir'], true), 403);

        if (is_string($request->input('nilai_program_realisasi'))) {
            $request->merge([
                'nilai_program_realisasi' => preg_replace('/\D/', '', $request->input('nilai_program_realisasi')),
            ]);
        }

        $request->validate([
            'nama_program_laporan' => ['required', 'string', 'max:255'],
            'nilai_program_realisasi' => ['required', 'numeric', 'min:0'],
            'no_dokumen_laporan' => ['required', 'string', 'max:255'],
            'nama_dokumen_laporan' => ['required', 'string', 'max:255'],
            'report_documents.*.nama' => ['nullable', 'string', 'max:255'],
            'report_documents.*.file' => ['nullable', 'file', 'max:10240'],
            'documentation_documents.*.nama' => ['nullable', 'string', 'max:255'],
            'documentation_documents.*.file' => ['nullable', 'file', 'max:10240'],
            'activity_photos' => ['nullable', 'array', 'max:30'],
            'activity_photos.*' => ['image', 'max:10240'],
        ]);
        $hasReport = $program->documents()->where('kategori_tahap', 'laporan_akhir')
            ->where('document_type', 'laporan_akhir')->exists();
        $hasDocumentation = $program->documents()->where('kategori_tahap', 'dokumentasi')
            ->where('document_type', 'dokumentasi_kegiatan')->exists();
        if ((! $hasReport && ! $request->hasFile('report_documents.laporan_akhir.file'))
            || (! $hasDocumentation && ! $request->hasFile('documentation_documents.dokumentasi_kegiatan.file'))) {
            return back()->withErrors([
                'report_documents.laporan_akhir.file' => 'Dokumen Laporan Akhir dan Dokumentasi Kegiatan wajib tersedia.',
            ])->withInput();
        }

        $from = $program->status;
        $metadata = $request->only([
            'nama_program_laporan',
            'nilai_program_realisasi',
            'no_dokumen_laporan',
            'nama_dokumen_laporan',
        ]);
        DB::transaction(function () use ($request, $program, $from, $metadata): void {
            $this->changes->storeStageDocuments($request, $program, 'laporan_akhir', 'report_documents');
            $this->changes->storeStageDocuments($request, $program, 'dokumentasi', 'documentation_documents');
            foreach ($request->file('activity_photos', []) as $photo) {
                $program->documents()->create([
                    'document_type' => 'foto_kegiatan',
                    'kategori_tahap' => 'dokumentasi',
                    'nama_dokumen' => $photo->getClientOriginalName(),
                    'nama_file_asli' => $photo->getClientOriginalName(),
                    'file_path' => $photo->store("programs/{$program->id}/documents", 'local'),
                    'uploaded_at' => now(),
                ]);
            }
            $program->update([
                ...$metadata,
                'realisasi_anggaran' => $metadata['nilai_program_realisasi'],
                'status' => 'menunggu_review_laporan_akhir',
                'catatan_terakhir' => null,
            ]);
            app(ProgramWorkflow::class)->log(
                $program,
                $from,
                $program->status,
                $request->user()->id,
            );
        });
        app(SubmissionNotificationService::class)->programWorkflowSubmitted($program, 'laporan_akhir');

        return back()->with('success', 'Laporan Akhir dan dokumentasi berhasil diajukan.');
    }

    public function downloadDocument(Request $request, ProgramDocument $document)
    {
        return $this->documents->downloadDocument($request, $document);
    }

    public function viewDocument(Request $request, ProgramDocument $document)
    {
        return $this->documents->viewDocument($request, $document);
    }

    public function destroyDocument(Request $request, ProgramDocument $document): RedirectResponse
    {
        return $this->documents->destroyDocument($request, $document);
    }
}
