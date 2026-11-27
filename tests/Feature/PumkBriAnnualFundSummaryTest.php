<?php

namespace Tests\Feature;

use App\Models\PumkBriAnnualFundSummary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PumkBriAnnualFundSummaryTest extends TestCase
{
    use RefreshDatabase;

    public function test_annual_fund_summary_is_upserted_and_calculates_remaining_funds(): void
    {
        $this->actingAs(User::factory()->create([
            'role' => 'pumk_admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]), 'pumk');

        $route = 'pumk-admin.umkm-binaan-bri.pumk-bri.annual-fund.store';
        $this->post(route($route), [
            'tahun' => 2025,
            'total_dana_ditransfer' => '1.000.000.000',
            'angsuran_pokok' => '200.000.000',
            'jasmin' => '20.000.000',
            'penyaluran' => '900.000.000',
            'os' => '250.000.000',
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'));

        $summary = PumkBriAnnualFundSummary::query()->where('tahun', 2025)->firstOrFail();
        $this->assertSame('70000000', (string) $summary->sisa_dana_akhir);

        $this->post(route($route), [
            'tahun' => 2025,
            'total_dana_ditransfer' => '2.000.000.000',
            'angsuran_pokok' => '300.000.000',
            'jasmin' => '50.000.000',
            'penyaluran' => '1.000.000.000',
            'os' => '1.200.000.000',
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'));

        $this->assertSame(1, PumkBriAnnualFundSummary::query()->where('tahun', 2025)->count());
        $this->assertSame('150000000', (string) $summary->fresh()->sisa_dana_akhir);

        $this->get(route('pumk-admin.umkm-binaan-bri.pumk-bri.index'))
            ->assertOk()
            ->assertSee('Perhitungan Dana Tahunan PUMK BRI')
            ->assertSee('Jasmin (Jasa Administrasi)')
            ->assertSee('OS (Outstanding)')
            ->assertSee('2.000.000.000')
            ->assertSee('Rp 150.000.000')
            ->assertSee('data-year="2025"', false);
    }
}
