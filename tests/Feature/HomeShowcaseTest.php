<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use App\Models\Pillar;
use App\Models\Program;
use App\Models\PumkBriPeriod;
use App\Models\PumkBriRealization;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\User;
use App\Services\PublicPages\HomeShowcaseData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class HomeShowcaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_home_is_news_only_and_role_homes_keep_showcase_charts(): void
    {
        NewsItem::query()->create([
            'title' => 'Berita Publik Home',
            'caption' => 'Caption berita untuk pengunjung dan seluruh role.',
            'content' => 'Isi lengkap berita publik.',
            'source' => 'Lingkungan',
            'published_date' => '26 November 2026',
            'status' => 'published',
        ]);
        NewsItem::query()->create([
            'title' => 'Draft Rahasia',
            'caption' => 'Konten yang belum boleh dilihat publik.',
            'content' => 'Isi draft.',
            'source' => 'Draft',
            'published_date' => '26 November 2026',
            'status' => 'draft',
        ]);

        $this->get(route('entry'))
            ->assertOk()
            ->assertSee('Portal Informasi TJSL &amp; PUMK', false)
            ->assertSee('Login')
            ->assertSee('images/LOGO TJSL.png')
            ->assertSee('images/logo/inka-footer-white.png')
            ->assertSee('RESOURCES')
            ->assertSee('CONTACT')
            ->assertSee('(+62) 811-310-8585')
            ->assertSee('tjsl@inka.co.id')
            ->assertSee('@tjsl.inka')
            ->assertSee('href="'.route('entry').'"', false)
            ->assertSee('href="'.route('teras').'"', false)
            ->assertSee('href="'.route('program.overview').'"', false)
            ->assertSee('id="home-showcase"', false)
            ->assertSee('Berita Publik Home')
            ->assertDontSee('Draft Rahasia')
            ->assertSee('News &amp; Kegiatan TJSL', false)
            ->assertSee('Kabar terbaru')
            ->assertDontSee('Grafik &amp; Capaian', false)
            ->assertDontSee('role="tablist"', false)
            ->assertDontSee('data-home-chart=', false);

        $roleHomes = [
            ['admin.home', 'admin'],
            ['superadmin.home', 'super_admin'],
            ['pumk-admin.home', 'pumk_admin'],
            ['home', 'user'],
        ];

        foreach ($roleHomes as [$route, $role]) {
            $user = User::factory()->create([
                'role' => $role,
                'is_admin' => $role === 'admin' || $role === 'super_admin',
                'is_active' => true,
                'must_change_password' => false,
            ]);
            $guard = match ($role) {
                'admin' => 'admin',
                'super_admin' => 'superadmin',
                'pumk_admin' => 'pumk',
                default => 'web',
            };

            $this->actingAs($user, $guard)
                ->get(route($route))
                ->assertOk()
                ->assertSee('images/LOGO TJSL.png')
                ->assertSee('id="home-showcase"', false)
                ->assertSee('Berita Publik Home')
                ->assertDontSee('Draft Rahasia')
                ->assertSee('Grafik &amp; Capaian', false)
                ->assertSee('data-home-chart="program-pillars"', false);
        }
    }

    public function test_public_charts_aggregate_program_regular_umkm_and_bri_data(): void
    {
        $pillar = Pillar::query()->create([
            'name' => 'Sosial',
            'slug' => 'sosial',
            'color_hex' => '#2563eb',
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'must_change_password' => false,
        ]);
        Program::query()->create([
            'slug' => 'program-publik-home',
            'pillar_id' => $pillar->id,
            'nama_program' => 'Program Publik Home',
            'deskripsi_program' => 'Deskripsi',
            'sasaran_program' => 'Sasaran',
            'lokasi_program' => 'Madiun',
            'mitra_program' => 'Mitra',
            'rencana_anggaran' => 100000,
            'tujuan_program' => 'Tujuan',
            'status' => 'pelaksanaan',
            'created_by' => $admin->id,
        ]);

        $regular = $this->mitra('Mitra Reguler Grafik', 'reguler');
        $this->loan($regular, 'bermasalah', 120000);
        $bri = $this->mitra('Mitra BRI Grafik', 'bri');
        $this->loan($bri, 'macet', 90000);

        $period = PumkBriPeriod::query()->create([
            'periode_tahun' => 2026,
            'anggaran_rka' => 200000,
        ]);
        PumkBriRealization::query()->create([
            'pumk_bri_period_id' => $period->id,
            'uraian' => 'Realisasi grafik',
            'waktu' => '2026-03-10',
            'pic' => 'PIC',
            'nominal' => 75000,
        ]);

        $data = app(HomeShowcaseData::class)->build();

        $this->assertSame(1, $data['programs']['total']);
        $this->assertSame(1, $data['programs']['pillars']->firstWhere('label', 'Sosial')['total']);
        $this->assertSame(1, $data['programs']['statuses']->firstWhere('label', 'Pelaksanaan')['total']);
        $this->assertSame(1, $data['regular']['total_mitra']);
        $this->assertSame(120000.0, $data['regular']['total_penyaluran']);
        $this->assertSame('Kota Madiun', $data['regular']['regions'][0]['label']);
        $this->assertSame('Pertanian', $data['regular']['sectors'][0]['label']);
        $this->assertSame(1, collect($data['regular']['quality'])->firstWhere('key', 'bermasalah')['total']);
        $this->assertSame(1, $data['bri']['total_mitra']);
        $this->assertSame(200000.0, $data['bri']['total_rka']);
        $this->assertSame(75000.0, $data['bri']['total_realization']);
        $this->assertSame('2026', $data['bri']['periods'][0]['year']);
        $this->assertSame(1, collect($data['bri']['quality'])->firstWhere('key', 'macet')['total']);
    }

    private function mitra(string $name, string $category): PumkMitra
    {
        return PumkMitra::query()->create([
            'nama_mitra' => $name,
            'kategori_binaan' => $category,
            'kabupaten_kota' => 'Kota Madiun',
            'sektor_sumber' => 'Pertanian',
            'source_key' => (string) Str::uuid(),
            'is_active' => true,
        ]);
    }

    private function loan(PumkMitra $mitra, string $quality, int $principal): PumkPinjaman
    {
        return PumkPinjaman::query()->create([
            'mitra_id' => $mitra->id,
            'pinjaman_pokok' => $principal,
            'pinjaman_bunga' => 1000,
            'kolektibilitas' => $quality,
            'is_active' => true,
            'status' => PumkPinjaman::STATUS_AKTIF,
            'source_key' => (string) Str::uuid(),
        ]);
    }
}
