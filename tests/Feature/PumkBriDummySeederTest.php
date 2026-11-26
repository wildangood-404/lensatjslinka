<?php

namespace Tests\Feature;

use App\Models\PumkBriPeriod;
use App\Models\PumkBriRealization;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\User;
use Database\Seeders\PumkBriDummySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PumkBriDummySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_seeds_bri_periods_realizations_and_eight_complete_mitra_idempotently(): void
    {
        Storage::fake('local');
        $this->seed(PumkBriDummySeeder::class);

        $this->assertSame(2, PumkBriPeriod::query()->count());
        $this->assertSame(4, PumkBriRealization::query()->count());
        $this->assertSame(8, PumkMitra::query()->where('kategori_binaan', 'bri')->count());
        $this->assertSame(8, PumkPinjaman::query()->count());
        $this->assertDatabaseHas('pumk_bri_periods', [
            'periode_tahun' => 2025,
            'anggaran_rka' => 1500000000,
        ]);
        $this->assertDatabaseHas('pumk_bri_periods', [
            'periode_tahun' => 2026,
            'anggaran_rka' => 2000000000,
        ]);
        $this->assertDatabaseHas('pumk_bri_realizations', [
            'dokumen_path' => 'pumk-bri/realisasi-2025-t1.pdf',
            'nominal' => 650000000,
        ]);
        $this->assertDatabaseHas('pumk_bri_realizations', [
            'uraian' => 'Penyaluran Dana Bergulir PUMK Triwulan II Wilayah Ngawi & Magetan',
            'dokumen_path' => null,
        ]);
        $this->assertSame(3, PumkBriRealization::query()->whereNotNull('dokumen_path')->count());

        $mitra = PumkMitra::query()
            ->where('kategori_binaan', 'bri')
            ->with('pinjaman')
            ->get();
        foreach ($mitra as $record) {
            $this->assertMatchesRegularExpression('/^\d{16}$/', (string) $record->nik);
            $this->assertNotEmpty($record->jenis_kelamin);
            $this->assertNotEmpty($record->tempat_lahir);
            $this->assertNotNull($record->tanggal_lahir);
            $this->assertNotEmpty($record->no_telepon_encrypted);
            $this->assertNotEmpty($record->email);
            $this->assertNotEmpty($record->alamat_ktp_lengkap);
            $this->assertNotEmpty($record->alamat_domisili_lengkap);
            $this->assertNotEmpty($record->alamat_usaha_lengkap);
            $this->assertNotEmpty($record->pinjaman->first()?->spj_awal);
        }

        $this->seed(PumkBriDummySeeder::class);
        $this->assertSame(2, PumkBriPeriod::query()->count());
        $this->assertSame(4, PumkBriRealization::query()->count());
        $this->assertSame(8, PumkMitra::query()->where('kategori_binaan', 'bri')->count());
        $this->assertSame(8, PumkPinjaman::query()->count());
    }

    public function test_bri_profile_only_renders_the_full_profile_and_legacy_planning_route_remains_available(): void
    {
        $this->seed(PumkBriDummySeeder::class);
        $this->actingAs(User::factory()->create([
            'role' => 'pumk_admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]), 'pumk');

        $mitra = PumkMitra::query()->where('nama_mitra', 'CV Sumber Rejeki Pangan')->firstOrFail();
        $this->get(route('pumk-admin.umkm-binaan-bri.show', $mitra))
            ->assertOk()
            ->assertSee('Identitas Utama & Biodata Pemilik')
            ->assertSee('Berkas & Dokumen UMKM Binaan')
            ->assertDontSee('receivable-tab', false)
            ->assertDontSee('monitoring-tab', false);

        $this->get(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'))
            ->assertOk()
            ->assertSee('Persentase (%)')
            ->assertSee('91,33%')
            ->assertSee('70,00%')
            ->assertSee('79,14%');

        $this->get(route('pumk-admin.bri-planning.index'))->assertOk();
    }
}
