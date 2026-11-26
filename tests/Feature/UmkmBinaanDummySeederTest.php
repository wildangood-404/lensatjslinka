<?php

namespace Tests\Feature;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkSektorUsaha;
use App\Models\PumkWilayah;
use App\Services\Pumk\PiutangCalculator;
use Database\Seeders\UmkmBinaanDummySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UmkmBinaanDummySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_ten_complete_idempotent_umkm_profiles_and_loan_snapshots(): void
    {
        $this->seed(UmkmBinaanDummySeeder::class);

        $this->assertSame(10, PumkMitra::query()->count());
        $this->assertSame(10, PumkPinjaman::query()->count());
        $this->assertSame(6, PumkWilayah::query()->count());
        $this->assertSame(10, PumkSektorUsaha::query()->count());
        $this->assertEqualsCanonicalizing(
            ['lancar', 'kurang_lancar', 'diragukan', 'macet'],
            PumkPinjaman::query()->distinct()->orderBy('kolektibilitas')->pluck('kolektibilitas')->all(),
        );

        $mitra = PumkMitra::query()->where('nama_mitra', 'CV Boga Mandiri Nusantara')->firstOrFail();
        $this->assertSame('9900000000000001', $mitra->nik);
        $this->assertSame('Budi Santoso', $mitra->nama_pemilik);
        $this->assertSame('081300000001', $mitra->no_telepon_encrypted);
        $this->assertSame('Kota Madiun', $mitra->alamat_ktp_kabupaten_kota);
        $this->assertSame('Kota Madiun', $mitra->alamat_domisili_kabupaten_kota);
        $this->assertSame('Kota Madiun', $mitra->alamat_usaha_kabupaten_kota);

        $loan = $mitra->pinjaman()->firstOrFail();
        $position = app(PiutangCalculator::class)->hitungUntukPinjaman($loan);
        $this->assertSame('30000000.00', $position['sisa_pokok']);
        $this->assertSame('1500000.00', $position['sisa_bunga']);
        $this->assertSame('31500000.00', $position['total_sisa']);
        $this->assertSame('lancar', $position['kolektibilitas']);

        $paidLoan = PumkMitra::query()
            ->where('nama_mitra', 'Jasa Ekspedisi & Logistik Kilat')
            ->firstOrFail()
            ->pinjaman()
            ->firstOrFail();
        $this->assertSame(PumkPinjaman::STATUS_LUNAS, $paidLoan->status);
        $this->assertFalse($paidLoan->is_active);
        $this->assertSame('lunas', $paidLoan->status_pinjaman);
        $this->assertDatabaseHas('pumk_loan_closures', [
            'pinjaman_id' => $paidLoan->id,
            'closed_at' => '2026-01-15 12:00:00',
        ]);

        $calculatedCategories = PumkPinjaman::query()
            ->get()
            ->map(fn (PumkPinjaman $seededLoan) => app(PiutangCalculator::class)->hitungUntukPinjaman($seededLoan)['kolektibilitas'])
            ->unique()
            ->values()
            ->all();
        $this->assertEqualsCanonicalizing(['lancar', 'kurang_lancar', 'diragukan', 'macet'], $calculatedCategories);

        $this->seed(UmkmBinaanDummySeeder::class);
        $this->assertSame(10, PumkMitra::query()->count());
        $this->assertSame(10, PumkPinjaman::query()->count());
    }
}
