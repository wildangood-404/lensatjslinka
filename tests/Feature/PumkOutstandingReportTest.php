<?php

namespace Tests\Feature;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkBriFasilitas;
use App\Models\PumkBriMitra;
use App\Models\PumkBriSnapshotBulanan;
use App\Models\PumkSektorUsaha;
use App\Models\PumkWilayah;
use App\Services\Pumk\PumkLoanSnapshotService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PumkOutstandingReportTest extends TestCase
{
    use RefreshDatabase;

    public function test_report_groups_current_balances_and_counts_distinct_mitra(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');

        $west = PumkWilayah::create(['nama' => 'Wilayah Barat', 'slug' => 'wilayah-barat']);
        $east = PumkWilayah::create(['nama' => 'Wilayah Timur', 'slug' => 'wilayah-timur']);
        $trade = PumkSektorUsaha::create(['nama' => 'Perdagangan', 'slug' => 'perdagangan']);
        $services = PumkSektorUsaha::create(['nama' => 'Jasa', 'slug' => 'jasa']);
        $first = $this->mitra('Mitra Pertama', $west, $trade);
        $second = $this->mitra('Mitra Kedua', $west, $services);
        $third = $this->mitra('Mitra Ketiga', $east, $trade);

        $this->loan($first, 0);
        $this->loan($first, 2);
        $this->loan($second, 7);
        $this->loan($third, 10);

        $response = $this->get(route('pumk-admin.umkm-binaan.report-outstanding'));

        $response->assertOk()
            ->assertSee('Report Outstanding UMKM Binaan')
            ->assertSee('Rekapitulasi Outstanding Berdasarkan Wilayah')
            ->assertSee('Rekapitulasi Outstanding Berdasarkan Sektor Usaha')
            ->assertSee('Rekapitulasi Kualitas Piutang')
            ->assertSee('colspan="3"', false)
            ->assertSee('Cetak');

        $response->assertViewHas('regionRows', function (array $rows): bool {
            $west = collect($rows)->firstWhere('label', 'Wilayah Barat');

            return $west['mitra_count'] === 2
                && $west['principal'] === '2040.00'
                && $west['interest'] === '300.00'
                && $west['problem_count'] === 2
                && $west['problem_principal'] === '1420.00'
                && $west['problem_interest'] === '200.00';
        });

        $response->assertViewHas('regionTotal', fn (array $total): bool => $total['mitra_count'] === 3
            && $total['principal'] === '2860.00'
            && $total['interest'] === '400.00'
            && $total['problem_count'] === 3
            && $total['problem_principal'] === '2240.00'
            && $total['problem_interest'] === '300.00');
        $response->assertViewHas('qualityRows', fn (array $rows): bool => count($rows) === 5
            && array_column($rows, 'mitra_count') === [1, 1, 1, 1, 0]);
        $response->assertViewHas('qualityTotal', fn (array $total): bool => $total['mitra_count'] === 3
            && $total['principal'] === '2860.00');
    }

    public function test_bri_report_uses_latest_snapshot_period_and_aggregates_all_five_column_tables(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');

        $first = $this->briSnapshot('Mitra BRI A', 'BRI Unit Madiun', 'Perdagangan', 'Lancar', 100, 50, 1, 2026);
        $this->briSnapshot('Mitra BRI A', 'BRI Unit Madiun', 'Perdagangan', 'Lancar', 1000, 800, 2, 2026, $first);
        $this->briSnapshot('Mitra BRI A', 'BRI Unit Madiun', 'Perdagangan', 'Macet', 500, 200, 2, 2026, $first);
        $this->briSnapshot('Mitra BRI B', 'BRI Unit Madiun', 'Perdagangan', 'Kurang Lancar', 2000, 1200, 2, 2026);
        $this->briSnapshot('Mitra BRI C', 'BRI Unit Ngawi', 'Pertanian', 'Diragukan', 3000, 1500, 2, 2026);

        $region = PumkWilayah::create(['nama' => 'Wilayah Legacy', 'slug' => 'wilayah-legacy']);
        $sector = PumkSektorUsaha::create(['nama' => 'Sektor Legacy', 'slug' => 'sektor-legacy']);
        $legacyMitra = $this->mitra('Mitra BRI Lama', $region, $sector, 'bri');
        $this->problematicLoan($legacyMitra);

        $response = $this->get(route('pumk-admin.umkm-binaan-bri.report-outstanding'));
        $response->assertOk()
            ->assertSee('Februari 2026')
            ->assertSee('Wilayah (KC BRI)')
            ->assertSee('Nilai Realisasi (Penyaluran)')
            ->assertSee('BRI Unit Madiun')
            ->assertSee('Perdagangan')
            ->assertDontSee('Mitra BRI Lama')
            ->assertDontSee('Bermasalah');

        preg_match_all('/<th\b/', $response->getContent(), $headers);
        $this->assertCount(15, $headers[0]);

        $response->assertViewHas('regionRows', function (array $rows): bool {
            $madiun = collect($rows)->firstWhere('label', 'BRI Unit Madiun');

            return $madiun['mitra_count'] === 2
                && $madiun['realization'] === '3500.00'
                && $madiun['outstanding'] === '2200.00';
        });
        $response->assertViewHas('sectorRows', function (array $rows): bool {
            $trade = collect($rows)->firstWhere('label', 'Perdagangan');

            return $trade['mitra_count'] === 2
                && $trade['realization'] === '3500.00'
                && $trade['outstanding'] === '2200.00';
        });
        $response->assertViewHas('regionTotal', fn (array $total): bool => $total['mitra_count'] === 3
            && $total['realization'] === '6500.00'
            && $total['outstanding'] === '3700.00');
        $response->assertViewHas('qualityRows', fn (array $rows): bool => array_column($rows, 'label') === [
            'Lancar', 'Kurang Lancar', 'Diragukan', 'Macet',
        ] && array_column($rows, 'mitra_count') === [1, 1, 1, 1]
            && array_column($rows, 'realization') === ['1000.00', '2000.00', '3000.00', '500.00']);
        $response->assertViewHas('qualityTotal', fn (array $total): bool => $total['mitra_count'] === 4
            && $total['realization'] === '6500.00'
            && $total['outstanding'] === '3700.00');

        $this->get(route('pumk-admin.umkm-binaan-bri.report-outstanding', [
            'bulan' => 1,
            'tahun' => 2026,
        ]))->assertOk()
            ->assertSee('Januari 2026')
            ->assertViewHas('regionTotal', fn (array $total): bool => $total['mitra_count'] === 1
                && $total['realization'] === '100.00'
                && $total['outstanding'] === '50.00');
    }

    public function test_bermasalah_remains_a_quality_bucket_for_regular_outstanding_reports(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');

        $region = PumkWilayah::create(['nama' => 'Wilayah Bermasalah', 'slug' => 'wilayah-bermasalah']);
        $sector = PumkSektorUsaha::create(['nama' => 'Sektor Bermasalah', 'slug' => 'sektor-bermasalah']);
        $regular = $this->mitra('Mitra Bermasalah Reguler', $region, $sector);
        $this->problematicLoan($regular);

        $this->get(route('pumk-admin.umkm-binaan.report-outstanding'))
            ->assertOk()
            ->assertSee('Bermasalah')
            ->assertSee('quality-badge is-bermasalah', false)
            ->assertViewHas('qualityRows', fn (array $rows): bool => array_column($rows, 'label') === [
                'Lancar', 'Kurang Lancar', 'Diragukan', 'Macet', 'Bermasalah',
            ] && $rows[4]['mitra_count'] === 1
                && $rows[4]['principal'] === '1000.00'
                && $rows[4]['interest'] === '100.00')
            ->assertViewHas('qualityTotal', fn (array $total): bool => $total['mitra_count'] === 1
                && $total['principal'] === '1000.00')
            ->assertViewHas('regionTotal', fn (array $total): bool => $total['problem_count'] === 1
                && $total['problem_principal'] === '1000.00'
                && $total['problem_interest'] === '100.00')
            ->assertViewHas('sectorTotal', fn (array $total): bool => $total['problem_count'] === 1
                && $total['problem_principal'] === '1000.00');
    }

    public function test_umkm_binaan_list_alias_preserves_existing_mitra_route_and_dashboard_links(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');

        [$region, $sector] = [
            PumkWilayah::create(['nama' => 'Wilayah Profil', 'slug' => 'wilayah-profil']),
            PumkSektorUsaha::create(['nama' => 'Sektor Profil', 'slug' => 'sektor-profil']),
        ];
        $mitra = $this->mitra('Mitra Profil', $region, $sector);
        $this->loan($mitra, 0);

        $newListUrl = route('pumk-admin.umkm-binaan.index');
        $profileUrl = route('pumk-admin.umkm-binaan.show', $mitra);

        $this->get($newListUrl)
            ->assertOk()
            ->assertSee('Daftar UMKM Binaan')
            ->assertSee('+ Tambah UMKM Binaan')
            ->assertSee('placeholder="Cari wilayah mitra"', false)
            ->assertSee('grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4')
            ->assertSee('placeholder="Masukkan nama mitra"', false)
            ->assertSee('href="'.$profileUrl.'"', false);
        $this->get($profileUrl)
            ->assertOk()
            ->assertSee('Profil UMKM Binaan')
            ->assertSee('Mitra Profil')
            ->assertSee('Sektor Profil')
            ->assertSee('Kartu Piutang');
        $this->get(route('pumk-admin.mitra.index'))->assertOk();
        $dashboard = $this->get(route('pumk-admin.home'));
        $dashboard
            ->assertOk()
            ->assertSee('UMKM Binaan')
            ->assertSee('Report Outstanding')
            ->assertSee('Daftar UMKM Binaan')
            ->assertSee('href="'.$newListUrl.'"', false)
            ->assertSee('Lihat Daftar Mitra')
            ->assertSee('Tambah Mitra Binaan');
        $this->assertSame(3, substr_count($dashboard->getContent(), 'href="'.$newListUrl.'"'));
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

    private function mitra(string $name, PumkWilayah $wilayah, PumkSektorUsaha $sektor, string $category = 'reguler'): PumkMitra
    {
        return PumkMitra::create([
            'nama_mitra' => $name,
            'kategori_binaan' => $category,
            'wilayah_id' => $wilayah->id,
            'sektor_usaha_id' => $sektor->id,
            'source_key' => (string) Str::uuid(),
            'is_active' => true,
        ]);
    }

    private function loan(PumkMitra $mitra, int $monthsOverdue): PumkPinjaman
    {
        $paid = (380 - ($monthsOverdue * 20)).'.00';
        $remainingPrincipal = bcsub('1000.00', $paid, 2);
        $category = match ($monthsOverdue) {
            0 => 'lancar',
            2 => 'kurang_lancar',
            7 => 'diragukan',
            10 => 'macet',
        };
        $monthly = '20.00';
        $loan = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => (string) Str::uuid(),
            'status' => PumkPinjaman::STATUS_AKTIF,
            'is_active' => true,
            'pinjaman_pokok' => '1000.00',
            'pinjaman_bunga' => '100.00',
            'sisa_pokok' => $remainingPrincipal,
            'sisa_bunga' => '100.00',
            'total_sisa' => bcadd($remainingPrincipal, '100.00', 2),
            'mulai_angsuran' => '2025-01-01',
            'nilai_angsuran_bulanan' => $monthly,
            'kolektibilitas' => $monthsOverdue === 10 ? 'lancar' : $category,
            'source_updated_at' => '2026-07-31 23:59:59',
            'baseline_sumber' => [
                'sisa_pokok' => $remainingPrincipal,
                'sisa_bunga' => '100.00',
                'total_pokok_masuk' => $paid,
                'total_bunga_masuk' => '0.00',
                'total_denda_masuk' => '0.00',
                'formula_sumber' => [
                    'mulai_angsuran' => '2025-01-01',
                    'angsuran_bulanan' => $monthly,
                    'total_kewajiban' => '1100.00',
                    'tanggal_acuan' => '2026-07-31',
                    'pokok_masuk_raw' => $paid,
                    'bunga_masuk_raw' => '0.00',
                    'sisa_pokok_raw' => $remainingPrincipal,
                    'sisa_bunga_raw' => '100.00',
                    'total_sisa_raw' => bcadd($remainingPrincipal, '100.00', 2),
                ],
            ],
        ]);

        $loan->saldoAwal()->create([
            'cutoff_date' => '2026-07-31',
            'pokok_masuk' => $paid,
            'bunga_masuk' => '0.00',
            'denda' => '0.00',
        ]);

        return $loan;
    }

    private function problematicLoan(PumkMitra $mitra): PumkPinjaman
    {
        $loan = PumkPinjaman::create([
            'mitra_id' => $mitra->id,
            'source_key' => (string) Str::uuid(),
            'status' => PumkPinjaman::STATUS_AKTIF,
            'is_active' => true,
            'pinjaman_pokok' => '1000.00',
            'pinjaman_bunga' => '100.00',
            'kolektibilitas' => 'bermasalah',
        ]);
        app(PumkLoanSnapshotService::class)->saveImported($loan, [
            'sisa_pokok' => '1000.00',
            'sisa_bunga' => '100.00',
            'total_sisa' => '1100.00',
            'kolektibilitas' => 'bermasalah',
        ]);

        return $loan;
    }

    private function briSnapshot(
        string $mitraName,
        string $unit,
        string $sector,
        string $quality,
        int $realization,
        int $outstanding,
        int $month,
        int $year,
        ?PumkBriMitra $mitra = null,
    ): PumkBriMitra {
        $mitra ??= PumkBriMitra::create([
            'nama_mitra' => $mitraName,
            'source_key' => hash('sha256', $mitraName),
        ]);
        $facility = PumkBriFasilitas::create([
            'mitra_id' => $mitra->id,
            'reference_key' => (string) Str::uuid(),
        ]);
        $qualityCode = match ($quality) {
            'Lancar' => 'L',
            'Kurang Lancar' => 'KL',
            'Diragukan' => 'D',
            'Macet' => 'M',
        };
        PumkBriSnapshotBulanan::create([
            'mitra_id' => $mitra->id,
            'fasilitas_id' => $facility->id,
            'bulan' => $month,
            'tahun' => $year,
            'saldo_piutang' => $outstanding,
            'kolektibilitas_kode' => $qualityCode,
            'kolektibilitas_label' => $quality,
            'source_sheet' => 'test',
            'source_row' => 2,
            'nama_mitra_binaan' => $mitraName,
            'bri_unit' => $unit,
            'provinsi' => 'Jawa Timur',
            'kabupaten_kota' => $unit === 'BRI Unit Ngawi' ? 'Ngawi' : 'Kota Madiun',
            'sektor_ekonomi_bumn' => $sector,
            'jumlah_realisasi' => $realization,
            'outstanding' => $outstanding,
            'kolektibilitas_bumn' => $quality,
        ]);

        return $mitra;
    }
}
