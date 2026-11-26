<?php

namespace Tests\Feature;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class PumkUmkmBinaanBriTest extends TestCase
{
    use RefreshDatabase;

    public function test_bri_list_report_and_profile_are_isolated_from_regular_mitra(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');
        $regular = $this->mitra('Regular Mitra', 'reguler');
        $bri = $this->mitra('BRI Mitra', 'bri');
        $this->loan($regular);
        $this->loan($bri);

        $this->get(route('pumk-admin.umkm-binaan.index'))
            ->assertOk()
            ->assertSee('Regular Mitra')
            ->assertDontSee('BRI Mitra');
        $this->get(route('pumk-admin.umkm-binaan-bri.index'))
            ->assertOk()
            ->assertSee('Daftar UMKM Binaan Via BRI')
            ->assertSee('+ Tambah UMKM Binaan Via BRI')
            ->assertSee('BRI Mitra')
            ->assertDontSee('Regular Mitra');

        $this->get(route('pumk-admin.umkm-binaan.report-outstanding'))
            ->assertOk()
            ->assertViewHas('regionTotal', fn (array $total): bool => $total['mitra_count'] === 1);
        $this->get(route('pumk-admin.umkm-binaan-bri.report-outstanding'))
            ->assertOk()
            ->assertSee('Report Outstanding UMKM Binaan Via BRI')
            ->assertViewHas('regionTotal', fn (array $total): bool => $total['mitra_count'] === 1);

        $profileUrl = route('pumk-admin.umkm-binaan-bri.show', $bri);
        $this->get($profileUrl)
            ->assertOk()
            ->assertSee('Profil UMKM Binaan Via BRI')
            ->assertSee('Berkas & Dokumen UMKM Binaan')
            ->assertDontSee('receivable-tab', false)
            ->assertDontSee('monitoring-tab', false)
            ->assertDontSee('receivable-panel', false)
            ->assertDontSee('monitoring-panel', false)
            ->assertSee(route('pumk-admin.umkm-binaan-bri.edit', $bri), false)
            ->assertSee('return_to_profile=1', false);
        $this->get(route('pumk-admin.umkm-binaan.show', $regular))
            ->assertOk()
            ->assertSee('receivable-tab', false)
            ->assertSee('monitoring-tab', false);
        $this->get(route('pumk-admin.umkm-binaan-bri.edit', $bri))
            ->assertOk()
            ->assertSee('Edit UMKM Binaan Via BRI')
            ->assertSee('action="'.route('pumk-admin.umkm-binaan-bri.update', $bri).'"', false);
        $this->get(route('pumk-admin.umkm-binaan-bri.card', $bri))
            ->assertOk()
            ->assertSee('Kartu Piutang UMKM Binaan Via BRI');
        $this->get(route('pumk-admin.umkm-binaan-bri.show', $regular))->assertNotFound();
        $this->get(route('pumk-admin.umkm-binaan.show', $bri))->assertNotFound();
    }

    public function test_bri_form_and_excel_import_save_the_bri_category(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');

        $this->get(route('pumk-admin.umkm-binaan-bri.create'))
            ->assertOk()
            ->assertSee('Tambah UMKM Binaan Via BRI')
            ->assertSee('action="'.route('pumk-admin.umkm-binaan-bri.store').'"', false)
            ->assertSee('Import Excel (Input Massal)')
            ->assertSee('Bermasalah');

        $this->post(route('pumk-admin.umkm-binaan-bri.store'), [
            'nama_mitra' => 'Mitra BRI Manual',
            'financial_snapshot' => '1',
            'pinjaman_pokok' => 1000000,
            'pinjaman_bunga' => 100000,
            'sisa_pokok' => 800000,
            'sisa_bunga' => 80000,
            'total_sisa' => 880000,
            'kolektibilitas' => 'bermasalah',
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.show', PumkMitra::query()->where('nama_mitra', 'Mitra BRI Manual')->firstOrFail()));

        $this->assertDatabaseHas('pumk_mitra', [
            'nama_mitra' => 'Mitra BRI Manual',
            'kategori_binaan' => 'bri',
        ]);
        $manualMitra = PumkMitra::query()->where('nama_mitra', 'Mitra BRI Manual')->firstOrFail();
        $this->assertDatabaseHas('pumk_pinjaman', [
            'mitra_id' => $manualMitra->id,
            'kolektibilitas' => 'bermasalah',
        ]);
        $this->get(route('pumk-admin.umkm-binaan-bri.show', $manualMitra))
            ->assertOk()
            ->assertSee('Bermasalah')
            ->assertSee('umkm-status-badge is-critical', false);

        $regularDuplicate = PumkMitra::create([
            'nama_mitra' => 'Mitra BRI Import',
            'kategori_binaan' => 'reguler',
            'source_key' => (string) Str::uuid(),
            'is_active' => true,
        ]);
        $this->loan($regularDuplicate);
        $csv = implode("\n", [
            'UMKM Binaan,Alamat,Wilayah,Pemilik,Kontak,Sektor,Pencairan,Tahun,Kolektibilitas,Sisa Pinjaman (Pokok & Bunga),Sisa Pinjaman Pokok,Sisa Pinjaman Bunga,Denda,Status',
            'Mitra BRI Import,Alamat usaha,Kota Madiun,Pemilik BRI,081234567890,Jasa,1.000.000,2026,Bermasalah,750.000,500.000,250.000,0,Aktif',
        ]);
        $this->post(route('pumk-admin.umkm-binaan-bri.import'), [
            'import_file' => UploadedFile::fake()->createWithContent('mitra-bri.csv', $csv),
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.create'))
            ->assertSessionHas('import_result.imported', 1);

        $this->assertDatabaseHas('pumk_mitra', [
            'nama_mitra' => 'Mitra BRI Import',
            'kategori_binaan' => 'bri',
        ]);
        $briImport = PumkMitra::query()
            ->where('nama_mitra', 'Mitra BRI Import')
            ->where('kategori_binaan', 'bri')
            ->firstOrFail();
        $this->assertDatabaseHas('pumk_pinjaman', [
            'mitra_id' => $briImport->id,
            'kolektibilitas' => 'bermasalah',
        ]);
        $this->get(route('pumk-admin.umkm-binaan.index'))
            ->assertViewHas('mitraList', fn ($list): bool => $list->contains('id', $regularDuplicate->id)
                && ! $list->contains('id', $briImport->id));
        $this->get(route('pumk-admin.umkm-binaan-bri.index'))
            ->assertViewHas('mitraList', fn ($list): bool => $list->contains('id', $briImport->id)
                && ! $list->contains('id', $regularDuplicate->id));
        $this->get(route('pumk-admin.umkm-binaan-bri.index', ['kolektibilitas' => 'bermasalah']))
            ->assertOk()
            ->assertSee('Mitra BRI Manual')
            ->assertSee('Mitra BRI Import')
            ->assertSee('value="bermasalah" selected', false);
        $this->get(route('pumk-admin.umkm-binaan-bri.report-outstanding'))
            ->assertViewHas('qualityRows', fn (array $rows): bool => $rows[4]['label'] === 'Bermasalah'
                && $rows[4]['mitra_count'] === 2)
            ->assertViewHas('regionTotal', fn (array $total): bool => $total['problem_count'] === 2);
    }

    public function test_sidebar_replaces_upload_monitoring_with_bri_dropdown(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');

        $this->get(route('pumk-admin.home'))
            ->assertOk()
            ->assertSee('UMKM Binaan Via BRI')
            ->assertSee(route('pumk-admin.umkm-binaan-bri.report-outstanding'), false)
            ->assertSee(route('pumk-admin.umkm-binaan-bri.index'), false)
            ->assertDontSee('Upload Monitoring')
            ->assertDontSee('RKA & Realisasi BRI');
    }

    private function pumkAdmin(): User
    {
        return User::factory()->create([
            'role' => 'pumk_admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
    }

    private function mitra(string $name, string $category): PumkMitra
    {
        return PumkMitra::create([
            'nama_mitra' => $name,
            'kategori_binaan' => $category,
            'kabupaten_kota' => 'Kota Madiun',
            'source_key' => (string) Str::uuid(),
            'is_active' => true,
        ]);
    }

    private function loan(PumkMitra $mitra): PumkPinjaman
    {
        return PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => (string) Str::uuid(),
            'status' => PumkPinjaman::STATUS_AKTIF,
            'is_active' => true,
            'pinjaman_pokok' => '1000.00',
            'pinjaman_bunga' => '100.00',
            'sisa_pokok' => '500.00',
            'sisa_bunga' => '50.00',
            'total_sisa' => '550.00',
            'mulai_angsuran' => '2025-01-01',
            'nilai_angsuran_bulanan' => '20.00',
            'kolektibilitas' => 'lancar',
            'source_updated_at' => '2026-07-31 23:59:59',
            'baseline_sumber' => [
                'sisa_pokok' => '500.00',
                'sisa_bunga' => '50.00',
                'total_pokok_masuk' => '500.00',
                'total_bunga_masuk' => '50.00',
                'total_denda_masuk' => '0.00',
                'formula_sumber' => [
                    'mulai_angsuran' => '2025-01-01',
                    'angsuran_bulanan' => '20.00',
                    'total_kewajiban' => '1100.00',
                    'tanggal_acuan' => '2026-07-31',
                    'pokok_masuk_raw' => '500.00',
                    'bunga_masuk_raw' => '50.00',
                    'sisa_pokok_raw' => '500.00',
                    'sisa_bunga_raw' => '50.00',
                    'total_sisa_raw' => '550.00',
                ],
            ],
        ]);
    }
}
