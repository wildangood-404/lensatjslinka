<?php

namespace Tests\Feature;

use App\Models\Pillar;
use App\Models\Program;
use App\Models\User;
use Database\Seeders\PillarSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProgramSubmissionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private Pillar $pillar;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        (new PillarSeeder)->run();
        $this->pillar = Pillar::where('slug', 'sosial')->firstOrFail();
    }

    public function test_admin_and_superadmin_can_complete_all_submission_stages_with_revisions(): void
    {
        $response = $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $this->programPayload([
                'documents' => [
                    'proposal_pengajuan_program' => [
                        'nama' => 'Proposal Utama',
                        'file' => UploadedFile::fake()->createWithContent('proposal.xyz', 'proposal'),
                    ],
                ],
            ]));

        $program = Program::where('nama_program', 'Program Tiga Tahap')->firstOrFail();
        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('admin.programs.show', $program));
        $this->assertSame('menunggu_review_proposal', $program->status);
        $this->assertDatabaseHas('program_documents', [
            'program_id' => $program->id,
            'kategori_tahap' => 'proposal',
            'nama_file_asli' => 'proposal.xyz',
        ]);
        $program->update([
            'sasaran_program' => 'Sasaran lama',
            'lokasi_program' => 'Lokasi lama',
            'tujuan_program' => 'Tujuan lama',
        ]);
        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.programs.show', $program))
            ->assertOk()
            ->assertSee('PKS')
            ->assertSee('Sosial')
            ->assertSee('10.000.000')
            ->assertSee('Proposal Utama')
            ->assertSee('Review Pengajuan Proposal Program')
            ->assertSee('Riwayat Perubahan Status');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.review-proposal', $program), [
                'decision' => 'revise',
                'catatan' => 'Sasaran dan uraian proposal perlu diperjelas.',
            ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('revisi', $program->fresh()->status);
        $this->assertSame('Sasaran dan uraian proposal perlu diperjelas.', $program->fresh()->catatan_terakhir);
        $this->assertSame(2, $program->logs()->count());
        $proposal = $program->documents()->where('document_type', 'proposal_pengajuan_program')->firstOrFail();
        Storage::disk('local')->assertExists($proposal->file_path);
        $this->actingAs($this->admin, 'admin')
            ->delete(route('admin.program-documents.destroy', $proposal))
            ->assertRedirect();
        Storage::disk('local')->assertMissing($proposal->file_path);
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee('Sasaran dan uraian proposal perlu diperjelas.')
            ->assertSee('name="action" value="submit"', false)
            ->assertSee('Proposal Program');

        $this->actingAs($this->admin, 'admin')
            ->put(route('admin.programs.update', $program), $this->programPayload([
                'action' => 'submit',
                'documents' => [
                    'proposal_pengajuan_program' => [
                        'nama' => 'Proposal Diperbaiki',
                        'file' => UploadedFile::fake()->createWithContent('proposal-revisi.xyz', 'proposal revisi'),
                    ],
                ],
            ]))
            ->assertRedirect();
        $this->assertSame('menunggu_review_proposal', $program->fresh()->status);
        $this->assertSame('Sasaran lama', $program->fresh()->sasaran_program);
        $this->assertSame('Lokasi lama', $program->fresh()->lokasi_program);
        $this->assertSame('Tujuan lama', $program->fresh()->tujuan_program);

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.review-proposal', $program), ['decision' => 'approve'])
            ->assertRedirect();
        $this->assertSame('proposal_approve', $program->fresh()->status);
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee('Pilar Final')
            ->assertSee('Usulan Jenis Bantuan')
            ->assertSee('Kelengkapan Survei');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.tahap2.store', $program), [
                'kesesuaian_rka' => 'CID',
                'pilar_verifikasi' => 'Ekonomi',
                'no_tpb' => 'TPB 4 - Pendidikan Berkualitas',
                'jenis_program_tpb' => 'Pendidikan vokasi',
                'sisa_anggaran' => '8.000.000',
                'kebutuhan_program' => 'Peralatan belajar untuk peserta program.',
                'lokasi_program' => 'Madiun',
                'sasaran_program' => 'Siswa sekolah vokasi',
                'tanggal_pelaksanaan' => '2026-05-01',
                'jenis_bantuan_verifikasi' => ['uang', 'barang', 'jasa'],
                'sumber_pencairan' => 'kas_bon',
                'nama_pemilik_rekening' => 'Ani Putri',
                'nomor_rekening' => '123456789',
                'nama_bank' => 'Bank Inka',
                'jalur_pengadaan_barang' => 'proses_pengadaan',
                'sub_jalur_pengadaan' => 'non_logistik',
                'vendors' => [
                    ['nama_vendor' => 'PT Vendor Uji', 'rincian_barang' => 'Peralatan kelas', 'harga' => '3.500.000'],
                ],
                'kategori_jasa' => 'non_logistik',
                'keterangan_jasa_verifikasi' => 'Pelatihan pengelolaan alat',
                'service_vendors' => [
                    ['nama_vendor' => 'CV Jasa Uji', 'rincian_jasa' => 'Pelatihan pengelolaan alat', 'harga' => '1.500.000'],
                ],
                'survey_documents' => [
                    'kelengkapan_survei' => [
                        'nama' => 'Kelengkapan Survei',
                        'file' => UploadedFile::fake()->createWithContent('survei.txt', 'survey'),
                    ],
                ],
                'phase2_documentation' => [
                    UploadedFile::fake()->createWithContent('foto-survei.txt', 'dokumentasi survei'),
                ],
            ])->assertSessionHasNoErrors()->assertRedirect();
        $this->assertSame('menunggu_review_survei', $program->fresh()->status);
        $this->assertSame('Ekonomi', $program->fresh()->effective_pilar);
        $this->assertSame('Ekonomi', $program->fresh()->pillar->name);
        $this->assertSame('8000000.00', $program->fresh()->sisa_anggaran);
        $this->assertDatabaseHas('program_verification_vendors', [
            'program_id' => $program->id,
            'tipe' => 'barang',
            'nama_vendor' => 'PT Vendor Uji',
            'harga' => '3500000.00',
        ]);
        $this->assertDatabaseHas('program_verification_vendors', [
            'program_id' => $program->id,
            'tipe' => 'jasa',
            'nama_vendor' => 'CV Jasa Uji',
            'rincian_jasa' => 'Pelatihan pengelolaan alat',
            'harga' => '1500000.00',
        ]);
        $phaseTwoDocumentation = $program->documents()->where('document_type', 'dokumentasi_verifikasi')->firstOrFail();
        Storage::disk('local')->assertExists($phaseTwoDocumentation->file_path);
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee('foto-survei.txt')
            ->assertSee(route('admin.program-documents.view', $phaseTwoDocumentation), false);
        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.programs.show', $program))
            ->assertOk()
            ->assertSee('Hasil Verifikasi Program')
            ->assertSee('Pendidikan vokasi')
            ->assertSee('PT Vendor Uji')
            ->assertSee('CV Jasa Uji')
            ->assertSee('foto-survei.txt')
            ->assertSee(route('superadmin.program-documents.view', $phaseTwoDocumentation), false)
            ->assertSee('Pelatihan pengelolaan alat');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.review-survei', $program), [
                'decision' => 'revise',
                'catatan' => 'Tambahkan informasi lokasi survei.',
            ])->assertRedirect();
        $this->assertSame('perbaikan', $program->fresh()->status);
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee('foto-survei.txt')
            ->assertSee('form="delete-document-'.$phaseTwoDocumentation->id.'"', false);
        $this->delete(route('admin.program-documents.destroy', $phaseTwoDocumentation))
            ->assertRedirect();
        Storage::disk('local')->assertMissing($phaseTwoDocumentation->file_path);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.tahap2.store', $program), [
                'kesesuaian_rka' => 'CID',
                'pilar_verifikasi' => 'Ekonomi',
                'no_tpb' => 'TPB 4 - Pendidikan Berkualitas',
                'jenis_program_tpb' => 'Pendidikan vokasi',
                'sisa_anggaran' => '8.000.000',
                'kebutuhan_program' => 'Peralatan belajar untuk peserta program.',
                'lokasi_program' => 'Madiun',
                'sasaran_program' => 'Siswa sekolah vokasi',
                'tanggal_pelaksanaan' => '2026-05-01',
                'jenis_bantuan_verifikasi' => ['uang', 'barang', 'jasa'],
                'sumber_pencairan' => 'kas_bon',
                'nama_pemilik_rekening' => 'Ani Putri',
                'nomor_rekening' => '123456789',
                'nama_bank' => 'Bank Inka',
                'jalur_pengadaan_barang' => 'proses_pengadaan',
                'sub_jalur_pengadaan' => 'non_logistik',
                'vendors' => [
                    ['nama_vendor' => 'PT Vendor Uji', 'rincian_barang' => 'Peralatan kelas', 'harga' => '3.500.000'],
                ],
                'kategori_jasa' => 'non_logistik',
                'keterangan_jasa_verifikasi' => 'Pelatihan pengelolaan alat',
                'service_vendors' => [
                    ['nama_vendor' => 'CV Jasa Uji', 'rincian_jasa' => 'Pelatihan pengelolaan alat', 'harga' => '1.500.000'],
                ],
                'survey_documents' => [
                    'kelengkapan_survei' => [
                        'nama' => 'Survei Revisi',
                        'file' => UploadedFile::fake()->createWithContent('survei-revisi.txt', 'survey revisi'),
                    ],
                ],
                'phase2_documentation' => [
                    UploadedFile::fake()->createWithContent('foto-survei-revisi.txt', 'dokumentasi survei revisi'),
                ],
            ])->assertRedirect();
        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.review-survei', $program), ['decision' => 'approve'])
            ->assertRedirect();
        $this->assertSame('survei_disetujui', $program->fresh()->status);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.kasbon.update', $program), [
                'kasbon_status' => 'disetujui',
                'realisasi_anggaran' => 'Rp 12.500.000',
            ])->assertRedirect();
        $this->assertSame('kasbon_disetujui', $program->fresh()->status);
        $this->assertSame('12500000.00', $program->fresh()->realisasi_anggaran);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.laporan-akhir.store', $program), [
                'nama_program_laporan' => 'Program Tiga Tahap',
                'nilai_program_realisasi' => '12.500.000',
                'no_dokumen_laporan' => 'LAP/001/2026',
                'nama_dokumen_laporan' => 'Laporan Realisasi Program',
                'report_documents' => [
                    'laporan_akhir' => ['nama' => 'Laporan', 'file' => UploadedFile::fake()->createWithContent('laporan.csv', 'final')],
                ],
                'documentation_documents' => [
                    'dokumentasi_kegiatan' => ['nama' => 'Dokumentasi', 'file' => UploadedFile::fake()->createWithContent('foto.bin', 'activity')],
                ],
            ])->assertRedirect();
        $this->assertSame('menunggu_review_laporan_akhir', $program->fresh()->status);

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.review-laporan-akhir', $program), [
                'decision' => 'revise',
                'catatan' => 'Lengkapi uraian hasil kegiatan.',
            ])->assertRedirect();
        $this->assertSame('revisi_laporan_akhir', $program->fresh()->status);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.laporan-akhir.store', $program), [
                'nama_program_laporan' => 'Program Tiga Tahap',
                'nilai_program_realisasi' => '12.500.000',
                'no_dokumen_laporan' => 'LAP/001/2026',
                'nama_dokumen_laporan' => 'Laporan Realisasi Program',
                'report_documents' => [
                    'laporan_akhir' => ['nama' => 'Laporan Revisi', 'file' => UploadedFile::fake()->createWithContent('laporan-revisi.csv', 'final revised')],
                ],
            ])->assertRedirect();
        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.review-laporan-akhir', $program), ['decision' => 'approve'])
            ->assertRedirect();

        $this->assertSame('selesai', $program->fresh()->status);
        $this->assertSame(13, $program->logs()->count());
    }

    public function test_rejection_notes_are_required_and_a_rejected_cash_advance_cannot_continue(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $this->programPayload([
                'nama_program' => 'Program Ditolak',
                'documents' => [
                    'proposal_pengajuan_program' => ['nama' => 'Proposal', 'file' => UploadedFile::fake()->createWithContent('proposal.dat', 'data')],
                ],
            ]));
        $rejectedProgram = Program::where('nama_program', 'Program Ditolak')->firstOrFail();

        $this->actingAs($this->superAdmin, 'superadmin')
            ->post(route('superadmin.programs.review-proposal', $rejectedProgram), ['decision' => 'reject'])
            ->assertSessionHasErrors('catatan');
        $this->assertSame('menunggu_review_proposal', $rejectedProgram->fresh()->status);
        $this->post(route('superadmin.programs.review-proposal', $rejectedProgram), [
            'decision' => 'reject',
            'catatan' => 'Program tidak memenuhi kriteria.',
        ])->assertRedirect();
        $this->assertSame('ditolak', $rejectedProgram->fresh()->status);

        $kasbonRejected = Program::create([
            'slug' => 'program-kasbon-ditolak',
            'pillar_id' => $this->pillar->id,
            'jenis_kerjasama' => 'pks',
            'tipe_kerjasama' => 'PKS',
            'jenis_pengajuan' => 'proposal_tjsl',
            'kategori_program' => $this->pillar->name,
            'nama_program' => 'Program Kasbon Ditolak',
            'deskripsi_program' => 'Deskripsi',
            'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Internal TJSL INKA',
            'rencana_anggaran' => 1000000,
            'realisasi_anggaran' => 0,
            'tujuan_program' => '',
            'status' => 'survei_disetujui',
            'created_by' => $this->admin->id,
        ]);
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.kasbon.update', $kasbonRejected), ['kasbon_status' => 'ditolak'])
            ->assertSessionHasErrors('catatan_penolakan');
        $this->post(route('admin.programs.kasbon.update', $kasbonRejected), [
            'kasbon_status' => 'ditolak',
            'catatan_penolakan' => 'Kasbon tidak dapat diproses.',
        ])->assertRedirect();
        $this->assertSame('kasbon_ditolak', $kasbonRejected->fresh()->status);
        $this->post(route('admin.programs.laporan-akhir.store', $kasbonRejected))
            ->assertForbidden();
    }

    public function test_proposal_form_validates_external_partner_and_accepts_any_file_up_to_ten_megabytes(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.create'))
            ->assertOk()
            ->assertSee('External (Stakeholder)')
            ->assertSee('Nama Mitra')
            ->assertSee('name="no_surat"', false)
            ->assertSee('name="tanggal_surat"', false)
            ->assertSee('Tanggal Program')
            ->assertSee('Jumlah Dana (Rp)')
            ->assertSee('Nama Kontak Person')
            ->assertSee('Hukum & Tata Kelola');

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $this->programPayload([
                'jenis_pengajuan' => 'external_stakeholder',
                'nama_mitra' => '',
                'documents' => [
                    'proposal_pengajuan_program' => [
                        'nama' => 'Proposal Utama',
                        'file' => UploadedFile::fake()->createWithContent('proposal.pdf', 'proposal'),
                    ],
                ],
            ]))
            ->assertSessionHasErrors('nama_mitra');

        $payload = $this->programPayload([
            'jenis_pengajuan' => 'external_stakeholder',
            'nama_mitra' => 'Mitra Uji',
            'documents' => [
                'proposal_pengajuan_program' => [
                    'nama' => 'Proposal Utama',
                    'file' => UploadedFile::fake()->create('besar.unknown', 10241),
                ],
            ],
        ]);
        $this->post(route('admin.programs.store'), $payload)
            ->assertSessionHasErrors('documents.proposal_pengajuan_program.file');
    }

    public function test_submission_requires_assistance_details_and_external_letter_fields(): void
    {
        $payload = $this->programPayload([
            'jenis_pengajuan' => 'external_stakeholder',
            'nama_mitra' => 'Mitra Uji',
            'jenis_bantuan' => ['barang'],
            'assistance_items' => [
                'barang' => [['nama_item' => '', 'jumlah' => '', 'keterangan' => '']],
            ],
            'documents' => [
                'proposal_pengajuan_program' => [
                    'nama' => 'Proposal',
                    'file' => UploadedFile::fake()->createWithContent('proposal.pdf', 'proposal'),
                ],
            ],
        ]);
        unset($payload['no_surat'], $payload['tanggal_surat']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $payload)
            ->assertSessionHasErrors([
                'no_surat',
                'tanggal_surat',
                'assistance_items.barang.0.nama_item',
            ]);

        unset($payload['jenis_bantuan'], $payload['assistance_items']);
        $this->post(route('admin.programs.store'), $payload)
            ->assertSessionHasErrors('jenis_bantuan');
    }

    public function test_contact_person_is_optional_for_a_submitted_proposal(): void
    {
        $payload = $this->programPayload([
            'documents' => [
                'proposal_pengajuan_program' => [
                    'nama' => 'Proposal Program',
                    'file' => UploadedFile::fake()->createWithContent('proposal.pdf', 'proposal'),
                ],
            ],
        ]);
        unset($payload['pic_nama'], $payload['pic_telepon']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $payload)
            ->assertSessionHasNoErrors();

        $program = Program::where('nama_program', 'Program Tiga Tahap')->firstOrFail();
        $this->assertNull($program->pic_nama);
        $this->assertNull($program->pic_telepon);
    }

    public function test_operational_stage_updates_status_and_enables_final_report_without_superadmin_approval(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $this->programPayload([
                'documents' => [
                    'proposal_pengajuan_program' => [
                        'nama' => 'Proposal Operasional',
                        'file' => UploadedFile::fake()->createWithContent('proposal.txt', 'proposal'),
                    ],
                ],
            ]))
            ->assertSessionHasNoErrors();

        $program = Program::where('nama_program', 'Program Tiga Tahap')->firstOrFail();
        $program->update(['status' => 'survei_disetujui']);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee('Tahap 3 — Tahap Pengajuan')
            ->assertDontSee('Tahap 4 — Pelaksanaan Program');
        $this->post(route('admin.programs.pelaksanaan.update', $program), [
            'status_operasional' => 'pelaksanaan',
        ])->assertForbidden();

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.pengajuan.update', $program), [
                'jalur_pengajuan' => ['uang_tunai_kas_bon', 'barang_bekas_jasa'],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('admin.programs.show', $program).'#stage-4');

        $this->assertSame('survei_disetujui', $program->fresh()->status);
        $this->assertNull($program->fresh()->status_operasional);
        $this->assertSame(['uang_tunai_kas_bon', 'barang_bekas_jasa'], $program->fresh()->jalur_pengajuan);

        $this->post(route('admin.programs.pelaksanaan.update', $program), [
            'status_operasional' => 'tertunda',
            'catatan_operasional' => 'Menunggu jadwal mitra.',
        ])->assertSessionHasNoErrors();
        $this->assertSame('tertunda', $program->fresh()->status);
        $this->assertSame('Menunggu jadwal mitra.', $program->fresh()->catatan_operasional);

        $this->post(route('admin.programs.pelaksanaan.update', $program), [
            'status_operasional' => 'pelaksanaan',
        ])->assertSessionHasNoErrors()->assertRedirect(route('admin.programs.show', $program).'#stage-5');
        $this->assertSame('pelaksanaan', $program->fresh()->status);
        $this->assertSame('pelaksanaan', $program->fresh()->status_operasional);
        $this->assertNull($program->fresh()->catatan_operasional);

        $this->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee('Tahap 4 — Pelaksanaan Program')
            ->assertSee('Tahap 5 — Pelaporan Akhir')
            ->assertSee('Pelaporan Akhir')
            ->assertSee('Pelaksanaan')
            ->assertSee('name="activity_photos[]" accept="image/*" multiple', false)
            ->assertSee('Nama Dokumen');

        $onePixelPng = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC');
        $this->post(route('admin.programs.laporan-akhir.store', $program), [
            'nama_program_laporan' => 'Program Tiga Tahap Selesai',
            'nilai_program_realisasi' => '9.500.000',
            'no_dokumen_laporan' => 'LAP/002/2026',
            'nama_dokumen_laporan' => 'Laporan Pelaksanaan',
            'report_documents' => [
                'laporan_akhir' => ['nama' => 'Dokumen Realisasi', 'file' => UploadedFile::fake()->createWithContent('realisasi.txt', 'realization')],
            ],
            'documentation_documents' => [
                'dokumentasi_kegiatan' => ['nama' => 'Laporan Kegiatan', 'file' => UploadedFile::fake()->createWithContent('kegiatan.txt', 'activity')],
            ],
            'activity_photos' => [
                UploadedFile::fake()->createWithContent('foto-1.png', $onePixelPng),
                UploadedFile::fake()->createWithContent('foto-2.png', $onePixelPng),
            ],
        ])->assertSessionHasNoErrors();

        $this->assertSame('menunggu_review_laporan_akhir', $program->fresh()->status);
        $this->assertSame('Program Tiga Tahap Selesai', $program->fresh()->nama_program_laporan);
        $this->assertSame('9500000.00', $program->fresh()->nilai_program_realisasi);
        $this->assertSame('9500000.00', $program->fresh()->realisasi_anggaran);
        $this->assertSame(2, $program->documents()->where('document_type', 'foto_kegiatan')->count());

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.programs.show', $program))
            ->assertOk()
            ->assertSee('Laporan Pelaksanaan')
            ->assertSee('9.500.000')
            ->assertSee('LAP/002/2026');

        $photo = $program->documents()->where('document_type', 'foto_kegiatan')->firstOrFail();
        Storage::disk('local')->assertExists($photo->file_path);
        $this->post(route('superadmin.programs.review-laporan-akhir', $program), [
            'decision' => 'revise',
            'catatan' => 'Tambahkan foto kegiatan yang lebih jelas.',
        ])->assertRedirect();
        $this->assertSame('revisi_laporan_akhir', $program->fresh()->status);
        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSee('foto-1.png');
        $this->delete(route('admin.program-documents.destroy', $photo))
            ->assertRedirect();
        Storage::disk('local')->assertMissing($photo->file_path);
        $this->assertDatabaseMissing('program_documents', ['id' => $photo->id]);
    }

    public function test_admin_status_program_has_status_specific_quick_actions(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $this->programPayload([
                'documents' => [
                    'proposal_pengajuan_program' => [
                        'nama' => 'Proposal',
                        'file' => UploadedFile::fake()->createWithContent('proposal.txt', 'proposal'),
                    ],
                ],
            ]))->assertSessionHasNoErrors();

        $program = Program::where('nama_program', 'Program Tiga Tahap')->firstOrFail();
        $program->update(['status' => 'proposal_approve']);

        $this->get(route('admin.programs.index'))
            ->assertOk()
            ->assertSee('Lanjutkan Pengisian')
            ->assertSee(route('admin.programs.show', $program).'#stage-2-edit', false);

        $program->update(['status' => 'perbaikan']);
        $this->get(route('admin.programs.index'))
            ->assertOk()
            ->assertSee('Perbaiki Berkas')
            ->assertSee(route('admin.programs.show', $program).'#stage-2-edit', false);

        $program->update(['status' => 'menunggu_review_survei']);
        $this->get(route('admin.programs.index'))
            ->assertOk()
            ->assertSee('Lihat Detail')
            ->assertSee(route('admin.programs.show', $program), false);
    }

    public function test_logistics_assistance_does_not_require_or_store_vendor_rows(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $this->programPayload([
                'documents' => [
                    'proposal_pengajuan_program' => [
                        'nama' => 'Proposal Logistik',
                        'file' => UploadedFile::fake()->createWithContent('proposal-logistik.txt', 'proposal'),
                    ],
                ],
            ]))->assertSessionHasNoErrors();

        $program = Program::where('nama_program', 'Program Tiga Tahap')->firstOrFail();
        $program->update(['status' => 'proposal_approve']);

        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.tahap2.store', $program), [
                'kesesuaian_rka' => 'CID',
                'pilar_verifikasi' => 'Sosial',
                'kebutuhan_program' => 'Kebutuhan program logistik.',
                'lokasi_program' => 'Madiun',
                'sasaran_program' => 'Masyarakat',
                'tanggal_pelaksanaan' => '2026-05-01',
                'jenis_bantuan_verifikasi' => ['barang', 'jasa'],
                'jalur_pengadaan_barang' => 'proses_pengadaan',
                'sub_jalur_pengadaan' => 'logistik',
                'vendors' => [
                    ['nama_vendor' => 'Vendor Tidak Digunakan', 'rincian_barang' => 'Barang', 'harga' => '1000'],
                ],
                'kategori_jasa' => 'logistik',
                'keterangan_jasa_verifikasi' => 'Pengiriman perlengkapan.',
                'survey_documents' => [
                    'kelengkapan_survei' => [
                        'nama' => 'Kelengkapan Survei',
                        'file' => UploadedFile::fake()->createWithContent('survei.txt', 'survey'),
                    ],
                ],
            ])->assertSessionHasNoErrors();

        $this->assertDatabaseCount('program_verification_vendors', 0);
    }

    public function test_external_proposal_stores_and_displays_all_requested_assistance_details(): void
    {
        $this->actingAs($this->admin, 'admin')
            ->post(route('admin.programs.store'), $this->programPayload([
                'documents' => [
                    'proposal_pengajuan_program' => [
                        'nama' => 'Proposal Utama',
                        'file' => UploadedFile::fake()->createWithContent('proposal.pdf', 'proposal'),
                    ],
                ],
                'jenis_pengajuan' => 'external_stakeholder',
                'nama_mitra' => 'Mitra Uji',
                'no_surat' => '123/INKA/2026',
                'tanggal_surat' => '2026-03-19',
                'tanggal_program' => '2026-04-20',
                'jenis_bantuan' => ['uang_tunai', 'barang', 'jasa'],
                'nominal_uang_tunai' => '25.000.000',
                'assistance_items' => [
                    'barang' => [
                        ['nama_item' => 'Kursi', 'jumlah' => '10 Unit', 'keterangan' => 'Kursi lipat'],
                        ['nama_item' => 'Meja', 'jumlah' => '2 Unit', 'keterangan' => ''],
                    ],
                    'jasa' => [
                        ['nama_item' => 'Pelatihan UMKM', 'jumlah' => '', 'keterangan' => 'Pelatihan satu hari'],
                    ],
                ],
                'pic_nama' => 'Ani Putri',
                'pic_telepon' => '081234567890',
            ]))
            ->assertSessionHasNoErrors();

        $program = Program::where('nama_program', 'Program Tiga Tahap')->firstOrFail();
        $this->assertSame(['uang_tunai', 'barang', 'jasa'], $program->jenis_bantuan);
        $this->assertSame(25000000, $program->nominal_uang_tunai);
        $this->assertSame('25000000.00', $program->rencana_anggaran);
        $this->assertSame('2026-04-20', $program->tanggal_program->format('Y-m-d'));
        $this->assertDatabaseCount('program_assistance_items', 3);

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.programs.show', $program))
            ->assertOk()
            ->assertSeeText('Mitra Uji')
            ->assertSeeText('123/INKA/2026')
            ->assertSeeText('19/03/2026')
            ->assertSeeText('20/04/2026')
            ->assertSeeText('25.000.000')
            ->assertSeeText('Kursi')
            ->assertSeeText('10 Unit')
            ->assertSeeText('Pelatihan UMKM')
            ->assertSeeText('Ani Putri')
            ->assertSeeText('081234567890');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.programs.show', $program))
            ->assertOk()
            ->assertSee('Review Pengajuan Proposal Program')
            ->assertSee('Mitra Uji')
            ->assertSee('123/INKA/2026')
            ->assertSee('19/03/2026')
            ->assertSee('20/04/2026')
            ->assertSee('25.000.000')
            ->assertSee('Kursi')
            ->assertSee('10 Unit')
            ->assertSee('Pelatihan UMKM')
            ->assertSee('Ani Putri')
            ->assertSee('081234567890');
    }

    private function programPayload(array $overrides = []): array
    {
        return array_replace_recursive([
            'pillar_id' => $this->pillar->id,
            'tipe_kerjasama' => 'PKS',
            'jenis_kerjasama' => 'pks',
            'jenis_pengajuan' => 'proposal_tjsl',
            'nama_program' => 'Program Tiga Tahap',
            'deskripsi_program' => 'Deskripsi lengkap',
            'tanggal_program' => '2026-04-20',
            'jenis_bantuan' => ['uang_tunai'],
            'nominal_uang_tunai' => '10.000.000',
            'no_surat' => '123/INKA/2026',
            'tanggal_surat' => '2026-03-19',
            'pic_nama' => 'Ani Putri',
            'pic_telepon' => '081234567890',
            'documents' => [],
            'action' => 'submit',
        ], $overrides);
    }
}
