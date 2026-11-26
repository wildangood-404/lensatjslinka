<?php

namespace Tests\Feature;

use App\Models\PumkBriPeriod;
use App\Models\PumkBriRealization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PumkBriRealisasiTest extends TestCase
{
    use RefreshDatabase;

    public function test_bri_sidebar_orders_the_new_page_above_report_and_list(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');

        $this->get(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'))
            ->assertOk()
            ->assertSeeInOrder([
                'PUMK BRI',
                'Report Outstanding',
                'Daftar UMKM Binaan',
            ])
            ->assertSee('.pumk-bri-field input[type="file"].sr-only{position:absolute', false)
            ->assertSee('id="dokumen" name="dokumen" type="file" class="sr-only"', false)
            ->assertSee('Tabel Rekapitulasi Anggaran &amp; Realisasi PUMK BRI', false);

        $this->get(route('pumk-admin.bri-planning.index'))->assertOk();
    }

    public function test_period_upserts_and_accepts_multiple_realizations_with_optional_documents(): void
    {
        Storage::fake('local');
        $this->actingAs($this->pumkAdmin(), 'pumk');

        $this->post(route('pumk-admin.umkm-binaan-bri.pumk-bri.period.store'), [
            'periode_tahun' => 2025,
            'anggaran_rka' => '1.250.000.000',
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'));
        $period = PumkBriPeriod::query()->where('periode_tahun', 2025)->firstOrFail();

        $this->post(route('pumk-admin.umkm-binaan-bri.pumk-bri.period.store'), [
            'periode_tahun' => 2025,
            'anggaran_rka' => '2.000.000.000',
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'));
        $this->assertSame(1, PumkBriPeriod::query()->count());
        $this->assertSame('2000000000', (string) $period->fresh()->anggaran_rka);

        $this->post(route('pumk-admin.umkm-binaan-bri.pumk-bri.realization.store'), [
            'pumk_bri_period_id' => $period->id,
            'uraian' => 'Penyaluran tahap pertama',
            'waktu' => '2025-03-14',
            'pic' => 'PIC Pertama',
            'nominal' => '125.000.000',
            'dokumen' => UploadedFile::fake()->createWithContent('bukti.pdf', "%PDF-1.4\n1 0 obj\n<<>>\nendobj\n"),
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'));

        $this->post(route('pumk-admin.umkm-binaan-bri.pumk-bri.realization.store'), [
            'pumk_bri_period_id' => $period->id,
            'uraian' => 'Penyaluran tahap kedua',
            'waktu' => '2025-06-02',
            'pic' => 'PIC Kedua',
            'nominal' => '75.000.000',
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'));

        $this->assertSame(2, $period->realisasi()->count());
        $documented = PumkBriRealization::query()->where('uraian', 'Penyaluran tahap pertama')->firstOrFail();
        Storage::disk('local')->assertExists($documented->dokumen_path);

        $this->get(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'))
            ->assertOk()
            ->assertSee('rowspan="2"', false)
            ->assertSee('Penyaluran tahap pertama')
            ->assertSee('Penyaluran tahap kedua')
            ->assertSee('14/03/2025')
            ->assertSee('2.000.000.000')
            ->assertSee('200.000.000')
            ->assertSee('10,00%')
            ->assertSee('✓')
            ->assertSee('Dokumen belum diunggah')
            ->assertSee(route('pumk-admin.umkm-binaan-bri.pumk-bri.documents.view', $documented), false);

        $this->get(route('pumk-admin.umkm-binaan-bri.pumk-bri.documents.view', $documented))
            ->assertOk();
        $this->get(route('pumk-admin.umkm-binaan-bri.pumk-bri.documents.download', $documented))
            ->assertOk()
            ->assertDownload('bukti.pdf');
    }

    public function test_zero_rka_shows_zero_percentage_without_division_by_zero(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');

        $this->post(route('pumk-admin.umkm-binaan-bri.pumk-bri.period.store'), [
            'periode_tahun' => 2027,
            'anggaran_rka' => '0',
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'));

        $this->get(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'))
            ->assertOk()
            ->assertSee('Persentase (%)')
            ->assertSee('0,00%')
            ->assertSee('aria-label="Persentase realisasi periode 2027"', false)
            ->assertSee('aria-label="Persentase realisasi keseluruhan"', false);
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
}
