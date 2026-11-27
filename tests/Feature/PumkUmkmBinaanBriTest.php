<?php

namespace Tests\Feature;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkBriFasilitas;
use App\Models\PumkBriMitra;
use App\Models\PumkBriSnapshotBulanan;
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
        $this->snapshotMitra('Snapshot BRI Mitra', '2026-02-01', 800);

        $this->get(route('pumk-admin.umkm-binaan.index'))
            ->assertOk()
            ->assertSee('Regular Mitra')
            ->assertDontSee('BRI Mitra');
        $this->get(route('pumk-admin.umkm-binaan-bri.index'))
            ->assertOk()
            ->assertSee('Daftar UMKM Binaan Via BRI')
            ->assertSee('+ Tambah UMKM Binaan Via BRI')
            ->assertSee('Snapshot BRI Mitra')
            ->assertSee('BRI Unit Madiun')
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
            ->assertSee('action="'.route('pumk-admin.umkm-binaan-bri.import').'"', false)
            ->assertSee('name="bulan"', false)
            ->assertSee('name="tahun"', false)
            ->assertSee('accept=".xlsx,.xls,.csv"', false)
            ->assertSee('Unduh Template Excel')
            ->assertSee('Angsuran Bunga yang Diterima Giro')
            ->assertDontSee('Input Manual')
            ->assertDontSee('name="nama_mitra"', false);

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

        $csv = implode("\n", [
            'Kode Unit,BRI Unit,Nama Mitra Binaan,Alamat,Provinsi,Kabupaten/ Kota,Sektor Ekonomi BUMN,Jangka Waktu,Tanggal Realisasi,Tanggal Jatuh Tempo,Jumlah Realisasi (Rp),Outstanding (Rp),Kolektabilitas BUMN,Tanggal Lunas,Jenis Usaha,Accrued Interest,Angsuran Bunga yang Diterima Giro',
            '001,BRI Unit Madiun,Mitra BRI Import,Alamat usaha,Jawa Timur,Kota Madiun,Perdagangan,36 Bulan,15/01/2026,15/01/2029,1.000.000,750.000,Lancar,,Kuliner,10.000,5.000',
        ]);
        $this->post(route('pumk-admin.umkm-binaan-bri.import'), [
            'bulan' => 1,
            'tahun' => 2026,
            'import_file' => UploadedFile::fake()->createWithContent('mitra-bri.csv', $csv),
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.create'))
            ->assertSessionHas('import_result.status', 'imported');

        $briImport = PumkBriMitra::query()->where('nama_mitra', 'Mitra BRI Import')->firstOrFail();
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', [
            'mitra_id' => $briImport->id,
            'bulan' => 1,
            'tahun' => 2026,
            'kode_unit' => '001',
            'bri_unit' => 'BRI Unit Madiun',
            'nama_mitra_binaan' => 'Mitra BRI Import',
            'jumlah_realisasi' => 1000000,
            'outstanding' => 750000,
            'accrued_interest' => 10000,
            'angsuran_bunga_diterima_giro' => 5000,
        ]);
        $listResponse = $this->get(route('pumk-admin.umkm-binaan-bri.index'));
        $listResponse
            ->assertViewHas('mitraList', fn ($list): bool => $list->contains('id', $briImport->id))
            ->assertSee('Mitra BRI Import')
            ->assertDontSee('Status Pinjaman')
            ->assertDontSee('Edit Arsip')
            ->assertDontSee(route('pumk-admin.umkm-binaan-bri.edit', $briImport->id), false);
        preg_match_all('/<th\b/', $listResponse->getContent(), $tableHeaders);
        $this->assertCount(7, $tableHeaders[0]);
        $this->get(route('pumk-admin.umkm-binaan-bri.index', ['bulan' => 1, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('value="1" selected', false)
            ->assertSee('value="2026" selected', false);
        $profileResponse = $this->get(route('pumk-admin.umkm-binaan-bri.snapshot.show', [
            'mitra' => $briImport->id,
            'bulan' => 1,
            'tahun' => 2026,
        ]))->assertOk()
            ->assertSee('Riwayat Perbandingan Bulanan');
        foreach ([
            'Kode Unit', 'BRI Unit', 'Nama Mitra Binaan', 'Alamat', 'Provinsi', 'Kabupaten/Kota',
            'Sektor Ekonomi BUMN', 'Jenis Usaha', 'Jangka Waktu', 'Tanggal Realisasi',
            'Tanggal Jatuh Tempo', 'Jumlah Realisasi', 'Outstanding', 'Kolektabilitas BUMN',
            'Tanggal Lunas', 'Accrued Interest', 'Angsuran Bunga yang Diterima Giro',
        ] as $fieldLabel) {
            $profileResponse->assertSee($fieldLabel);
        }
        $this->get(route('pumk-admin.umkm-binaan-bri.report-outstanding'))
            ->assertViewHas('regionTotal', fn (array $total): bool => $total['mitra_count'] === 1
                && $total['realization'] === '1000000.00'
                && $total['outstanding'] === '750000.00');
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

    private function snapshotMitra(string $name, string $period, int $outstanding): PumkBriMitra
    {
        $mitra = PumkBriMitra::create([
            'nama_mitra' => $name,
            'source_key' => hash('sha256', $name),
        ]);
        $facility = PumkBriFasilitas::create([
            'mitra_id' => $mitra->id,
            'reference_key' => (string) Str::uuid(),
        ]);
        $date = \Illuminate\Support\Carbon::parse($period);
        PumkBriSnapshotBulanan::create([
            'mitra_id' => $mitra->id,
            'fasilitas_id' => $facility->id,
            'bulan' => $date->month,
            'tahun' => $date->year,
            'saldo_piutang' => $outstanding,
            'kolektibilitas_kode' => 'L',
            'kolektibilitas_label' => 'Lancar',
            'source_sheet' => 'test',
            'source_row' => 2,
            'nama_mitra_sumber' => $name,
            'nama_mitra_binaan' => $name,
            'bri_unit' => 'BRI Unit Madiun',
            'kabupaten_kota' => 'Kota Madiun',
            'outstanding' => $outstanding,
            'kolektibilitas_bumn' => 'Lancar',
        ]);

        return $mitra;
    }
}
