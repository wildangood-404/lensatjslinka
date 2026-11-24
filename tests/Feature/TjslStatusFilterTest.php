<?php

namespace Tests\Feature;

use App\Models\BantuanCsr;
use App\Models\Pillar;
use App\Models\Program;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TjslStatusFilterTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $superAdmin;

    private Pillar $pillar;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->create([
            'role' => 'admin', 'is_active' => true, 'must_change_password' => false,
        ]);
        $this->superAdmin = User::factory()->create([
            'role' => 'super_admin', 'is_active' => true, 'must_change_password' => false,
        ]);
        $this->pillar = Pillar::create(['name' => 'Sosial', 'slug' => 'sosial', 'color_hex' => '#2563eb']);
    }

    public function test_admin_program_filter_uses_latest_statuses_and_keeps_legacy_program_states(): void
    {
        $this->program('Program Draft', 'draft');
        $this->program('Program Menunggu Proposal', 'menunggu_review_proposal');
        $this->program('Program Menunggu Tahap 1', 'pending_fase1');
        $this->program('Program Proposal Approve', 'proposal_approve');
        $this->program('Program Legacy Proposal Approved', 'approved_fase1');
        $this->program('Program BAST Ditolak', 'approved_fase1', 'BAST perlu diperbaiki.');
        $this->program('Program Menunggu Survei', 'menunggu_review_survei');
        $this->program('Program Menunggu Tahap 2', 'pending_fase2');
        $this->program('Program Revisi Proposal', 'revisi');
        $this->program('Program Perbaikan Survei', 'perbaikan');
        $this->program('Program Survei Disetujui', 'survei_disetujui');
        $this->program('Program Tertunda', 'tertunda');
        $this->program('Program Pelaksanaan', 'pelaksanaan');
        $this->program('Program Kasbon Disetujui', 'kasbon_disetujui');
        $this->program('Program Kasbon Ditolak', 'kasbon_ditolak');
        $this->program('Program Menunggu Laporan Akhir', 'menunggu_review_laporan_akhir');
        $this->program('Program Revisi Laporan Akhir', 'revisi_laporan_akhir');
        $this->program('Program Ditolak', 'ditolak');
        $this->program('Program Ditolak Tahap 1', 'rejected_fase1');
        $this->program('Program Selesai', 'selesai');
        $this->program('Program Selesai Legacy', 'completed');

        $base = $this->actingAs($this->admin, 'admin')->get(route('admin.programs.index'));
        $base->assertOk()
            ->assertSee('Semua Status Aktif')
            ->assertSee('Menunggu Review Pengajuan Proposal Program')
            ->assertSee('Tertunda')
            ->assertSee('Pelaksanaan')
            ->assertSee('Revisi Laporan Akhir')
            ->assertDontSee('ACC Tahap 1')
            ->assertDontSee('Program Ditolak Tahap 1')
            ->assertSee('text-amber-600', false)
            ->assertSee('text-blue-600', false)
            ->assertSee('text-red-600', false)
            ->assertSee('text-emerald-600', false)
            ->assertSee('text-slate-700', false);

        $this->get(route('admin.programs.index', ['status' => 'menunggu_review_proposal']))
            ->assertOk()->assertSee('Program Menunggu Proposal')->assertSee('Program Menunggu Tahap 1')
            ->assertDontSee('Program Proposal Approve');
        $this->get(route('admin.programs.index', ['status' => 'proposal_approve']))
            ->assertOk()->assertSee('Program Proposal Approve')->assertSee('Program Legacy Proposal Approved')
            ->assertDontSee('Program BAST Ditolak');
        $this->get(route('admin.programs.index', ['status' => 'menunggu_review_survei']))
            ->assertOk()->assertSee('Program Menunggu Survei')->assertSee('Program Menunggu Tahap 2');
        $this->get(route('admin.programs.index', ['status' => 'revisi']))
            ->assertOk()->assertSee('Program Revisi Proposal')->assertDontSee('Program Perbaikan Survei');
        $this->get(route('admin.programs.index', ['status' => 'perbaikan']))
            ->assertOk()->assertSee('Program Perbaikan Survei')->assertDontSee('Program Revisi Proposal');
        $this->get(route('admin.programs.index', ['status' => 'tertunda']))
            ->assertOk()->assertSee('Program Tertunda')->assertDontSee('Program Pelaksanaan');
        $this->get(route('admin.programs.index', ['status' => 'pelaksanaan']))
            ->assertOk()->assertSee('Program Pelaksanaan')->assertDontSee('Program Tertunda');
        $this->get(route('admin.programs.index', ['status' => 'kasbon_ditolak']))
            ->assertOk()->assertSee('Program Kasbon Ditolak')->assertDontSee('Program Ditolak');
        $this->get(route('admin.programs.index', ['status' => 'ditolak']))
            ->assertOk()->assertSee('Program Ditolak Tahap 1')->assertSee('Program Ditolak')
            ->assertSee('Program BAST Ditolak')->assertDontSee('Program Kasbon Ditolak')
            ->assertDontSee('Program Selesai');
        $this->get(route('admin.programs.index', ['status' => 'selesai']))
            ->assertOk()->assertSee('Program Selesai')->assertSee('Program Selesai Legacy')
            ->assertDontSee('Program Menunggu Tahap 1');
    }

    public function test_program_cards_use_pillar_colors_partner_names_and_role_specific_detail_links(): void
    {
        $pillars = [
            $this->pillar,
            Pillar::create(['name' => 'Ekonomi', 'slug' => 'ekonomi', 'color_hex' => '#f59e0b']),
            Pillar::create(['name' => 'Lingkungan', 'slug' => 'lingkungan', 'color_hex' => '#16a34a']),
            Pillar::create(['name' => 'Hukum & Tata Kelola', 'slug' => 'hukum-tata-kelola', 'color_hex' => '#dc2626']),
        ];
        $programs = [];

        foreach ($pillars as $index => $pillar) {
            $program = $this->program('Program Pilar '.$pillar->name, 'proposal_approve');
            $program->update([
                'pillar_id' => $pillar->id,
                'kategori_program' => $pillar->name,
                'jenis_pengajuan' => $index === 1 ? 'external_stakeholder' : 'proposal_tjsl',
                'nama_mitra' => $index === 1 ? 'Mitra Eksternal' : null,
            ]);
            $programs[] = $program;
        }

        $adminList = $this->actingAs($this->admin, 'admin')->get(route('admin.programs.index'));
        $adminList->assertOk()
            ->assertSee('TJSL')
            ->assertSee('Mitra Eksternal')
            ->assertSee('Pilar: Sosial')
            ->assertSee('Pilar: Ekonomi')
            ->assertSee('bg-blue-600', false)
            ->assertSee('bg-amber-500', false)
            ->assertSee('bg-emerald-600', false)
            ->assertSee('bg-red-600', false)
            ->assertSee('submission-status-program-card', false)
            ->assertSee($programs[1]->updated_at->format('H.i d/m/Y'))
            ->assertSee(route('admin.programs.show', $programs[1]), false);

        $superAdminList = $this->actingAs($this->superAdmin, 'superadmin')->get(route('superadmin.programs.index'));
        $superAdminList->assertOk()
            ->assertSee('Mitra Eksternal')
            ->assertSee(route('superadmin.programs.show', $programs[1]), false);
    }

    public function test_superadmin_program_filter_is_submitted_only_and_keeps_pillar_query(): void
    {
        $this->program('Draft Privat', 'draft');
        $this->program('Pengajuan Menunggu', 'menunggu_review_proposal');
        $this->program('Pengajuan Ditolak', 'rejected_fase1');
        $this->program('Pengajuan Selesai', 'selesai');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.programs.index'))
            ->assertOk()->assertDontSee('Draft Privat')->assertDontSee('Pengajuan Ditolak');
        $this->get(route('superadmin.programs.index', ['status' => 'ditolak', 'pillar' => 'sosial']))
            ->assertOk()->assertSee('Pengajuan Ditolak')->assertDontSee('Draft Privat')
            ->assertSee('name="pillar" value="sosial"', false);
        $this->get(route('superadmin.programs.index', ['status' => 'draft']))
            ->assertRedirect()->assertSessionHasErrors('status');
    }

    public function test_admin_and_superadmin_assistance_filters_include_phase_one_and_bast_rejections(): void
    {
        $this->assistance('Bantuan Draft', 'draft');
        $this->assistance('Bantuan Menunggu', 'pending_fase1');
        $this->assistance('Bantuan ACC Tahap 1', 'approved_fase1');
        $this->assistance('Bantuan BAST Ditolak', 'approved_fase1', 'BAST belum lengkap.');
        $this->assistance('Bantuan Ditolak Tahap 1', 'rejected_fase1');
        $this->assistance('Bantuan Selesai', 'completed');

        $this->actingAs($this->admin, 'admin')
            ->get(route('admin.assistance.index', ['status' => 'rejected']))
            ->assertOk()->assertSee('Bantuan Ditolak Tahap 1')->assertSee('Bantuan BAST Ditolak')
            ->assertDontSee('Bantuan Selesai');

        $this->actingAs($this->superAdmin, 'superadmin')
            ->get(route('superadmin.assistance.index', ['status' => 'completed']))
            ->assertOk()->assertSee('Bantuan Selesai')->assertDontSee('Bantuan Draft')
            ->assertDontSee('Bantuan Menunggu');
        $this->get(route('superadmin.assistance.index', ['status' => 'rejected']))
            ->assertOk()->assertSee('Bantuan Ditolak Tahap 1')->assertSee('Bantuan BAST Ditolak')
            ->assertSee('BAST Ditolak');
    }

    private function program(string $name, string $status, ?string $phaseTwoReason = null): Program
    {
        return Program::create([
            'slug' => str($name)->slug(), 'pillar_id' => $this->pillar->id, 'jenis_kerjasama' => 'pks',
            'nama_program' => $name, 'deskripsi_program' => 'Deskripsi', 'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Madiun', 'mitra_program' => 'Mitra', 'rencana_anggaran' => 100,
            'status' => $status, 'fase2_rejected_reason' => $phaseTwoReason, 'created_by' => $this->admin->id,
        ]);
    }

    private function assistance(string $name, string $status, ?string $phaseTwoReason = null): BantuanCsr
    {
        return BantuanCsr::create([
            'nama_program_bantuan' => $name, 'deskripsi_bantuan' => 'Deskripsi',
            'pillar_id' => $this->pillar->id, 'rencana_anggaran' => 100, 'status' => $status,
            'fase2_rejected_reason' => $phaseTwoReason, 'created_by' => $this->admin->id,
        ]);
    }
}
