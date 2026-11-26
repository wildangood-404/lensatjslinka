<?php

namespace Tests\Feature;

use App\Models\PumkMitra;
use App\Models\UmkmMonitoring;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class UmkmMonitoringTest extends TestCase
{
    use RefreshDatabase;

    public function test_regular_profile_shows_monitoring_history_table_and_update_form(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');
        $mitra = $this->mitra();

        $this->get(route('pumk-admin.umkm-binaan.show', ['mitra' => $mitra, 'tab' => 'monitoring']))
            ->assertOk()
            ->assertSee('Riwayat Monitoring')
            ->assertSee('Periode Tahun')
            ->assertSee('Jenis Kunjungan')
            ->assertSee('Dokumentasi')
            ->assertSee('Update Monitoring')
            ->assertSee('Simpan Update Monitoring')
            ->assertSee('type="file" name="dokumentasi[]"', false)
            ->assertSee('multiple class="sr-only"', false);
    }

    public function test_monitoring_visit_saves_multiple_documents_and_keeps_private_preview_route(): void
    {
        Storage::fake('local');
        $this->actingAs($this->pumkAdmin(), 'pumk');
        $mitra = $this->mitra();
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p1sAAAAASUVORK5CYII=');
        $pdf = "%PDF-1.4\n";

        $response = $this->post(route('pumk-admin.umkm-binaan.monitoring.store', $mitra), [
            'periode_tahun' => 2026,
            'jenis_kunjungan' => 'Lainnya',
            'jenis_kunjungan_custom' => 'Survei lokasi usaha',
            'tindak_lanjut' => 'Jadwalkan pendampingan pemasaran digital.',
            'keterangan' => 'Produksi berjalan normal.',
            'dokumentasi' => [
                UploadedFile::fake()->createWithContent('lokasi-1.png', $png),
                UploadedFile::fake()->createWithContent('lokasi-2.png', $png),
                UploadedFile::fake()->createWithContent('laporan.pdf', $pdf),
            ],
        ]);

        $record = UmkmMonitoring::query()->where('mitra_id', $mitra->id)->firstOrFail();
        $response->assertRedirect(route('pumk-admin.umkm-binaan.show', ['mitra' => $mitra, 'tab' => 'monitoring']))
            ->assertSessionHas('success', 'Update monitoring berhasil disimpan.');
        $this->assertSame(2026, $record->periode_tahun);
        $this->assertSame('Survei lokasi usaha', $record->jenis_kunjungan);
        $this->assertCount(3, $record->dokumentasi);
        Storage::disk('local')->assertExists($record->dokumentasi[0]['path']);
        Storage::disk('local')->assertExists($record->dokumentasi[1]['path']);
        Storage::disk('local')->assertExists($record->dokumentasi[2]['path']);

        $this->get(route('pumk-admin.umkm-binaan.monitoring.document', [$mitra, $record, 0]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/png');
        $this->get(route('pumk-admin.umkm-binaan.monitoring.document', [$mitra, $record, 2]))
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf');
        $this->get(route('pumk-admin.umkm-binaan.show', ['mitra' => $mitra, 'tab' => 'monitoring']))
            ->assertOk()
            ->assertSee('Survei lokasi usaha')
            ->assertSee('Jadwalkan pendampingan pemasaran digital.')
            ->assertSee('Produksi berjalan normal.');
    }

    public function test_monitoring_upload_rejects_unsupported_document_formats(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');
        $mitra = $this->mitra();

        $this->from(route('pumk-admin.umkm-binaan.show', ['mitra' => $mitra, 'tab' => 'monitoring']))
            ->post(route('pumk-admin.umkm-binaan.monitoring.store', $mitra), [
                'periode_tahun' => 2026,
                'jenis_kunjungan' => 'Kunjungan Rutin',
                'tindak_lanjut' => 'Hubungi mitra kembali.',
                'dokumentasi' => [UploadedFile::fake()->createWithContent('catatan.txt', 'bukan dokumentasi yang diizinkan')],
            ])
            ->assertSessionHasErrors('dokumentasi.0');

        $this->assertDatabaseCount('umkm_monitorings', 0);
    }

    public function test_each_monitoring_file_is_limited_to_ten_megabytes(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');
        $mitra = $this->mitra();
        $oversizedPdf = "%PDF-1.4\n".str_repeat('0', 10 * 1024 * 1024);

        $this->from(route('pumk-admin.umkm-binaan.show', ['mitra' => $mitra, 'tab' => 'monitoring']))
            ->post(route('pumk-admin.umkm-binaan.monitoring.store', $mitra), [
                'periode_tahun' => 2026,
                'jenis_kunjungan' => 'Kunjungan Rutin',
                'tindak_lanjut' => 'Hubungi mitra kembali.',
                'dokumentasi' => [UploadedFile::fake()->createWithContent('terlalu-besar.pdf', $oversizedPdf)],
            ])
            ->assertSessionHasErrors('dokumentasi.0');

        $this->assertDatabaseCount('umkm_monitorings', 0);
    }

    public function test_monitoring_documents_cannot_be_read_through_another_partner_profile(): void
    {
        Storage::fake('local');
        $this->actingAs($this->pumkAdmin(), 'pumk');
        $mitra = $this->mitra();
        $otherMitra = $this->mitra('Mitra Lain');
        $path = 'umkm-monitorings/'.$mitra->id.'/private.png';
        Storage::disk('local')->put($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p1sAAAAASUVORK5CYII='));
        $record = $mitra->monitorings()->create([
            'periode_tahun' => 2026,
            'jenis_kunjungan' => 'Kunjungan Rutin',
            'tindak_lanjut' => 'Pemeriksaan rutin.',
            'dokumentasi' => [['path' => $path, 'name' => 'private.png', 'mime' => 'image/png']],
        ]);

        $this->get(route('pumk-admin.umkm-binaan.monitoring.document', [$otherMitra, $record, 0]))
            ->assertNotFound();
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

    private function mitra(string $name = 'Mitra Monitoring'): PumkMitra
    {
        return PumkMitra::create([
            'nama_mitra' => $name,
            'source_key' => hash('sha256', $name.microtime(true)),
            'is_active' => true,
        ]);
    }
}
