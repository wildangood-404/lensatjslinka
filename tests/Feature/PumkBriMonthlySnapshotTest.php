<?php

namespace Tests\Feature;

use App\Models\PumkBriMitra;
use App\Models\PumkBriSnapshotBulanan;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class PumkBriMonthlySnapshotTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->create([
            'role' => 'pumk_admin',
            'is_active' => true,
            'must_change_password' => false,
        ]), 'pumk');
    }

    public function test_reupload_replaces_only_the_selected_month_and_list_defaults_to_latest_period(): void
    {
        $this->uploadCsv(1, 2026, 750000)
            ->assertRedirect(route('pumk-admin.umkm-binaan-bri.create'))
            ->assertSessionHas('import_result.status', 'imported');
        $this->uploadCsv(2, 2026, 650000)
            ->assertRedirect(route('pumk-admin.umkm-binaan-bri.create'))
            ->assertSessionHas('import_result.status', 'imported');
        $this->uploadCsv(1, 2026, 700000)
            ->assertRedirect(route('pumk-admin.umkm-binaan-bri.create'))
            ->assertSessionHas('import_result.status', 'imported');

        $this->assertSame(1, PumkBriMitra::count());
        $this->assertSame(2, PumkBriSnapshotBulanan::count());
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', [
            'bulan' => 1,
            'tahun' => 2026,
            'outstanding' => 700000,
        ]);
        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', [
            'bulan' => 2,
            'tahun' => 2026,
            'outstanding' => 650000,
        ]);

        $this->get(route('pumk-admin.umkm-binaan-bri.index'))
            ->assertOk()
            ->assertSee('Februari 2026')
            ->assertSee('value="2" selected', false)
            ->assertSee('Rp650.000');
        $this->get(route('pumk-admin.umkm-binaan-bri.index', ['bulan' => 1, 'tahun' => 2026]))
            ->assertOk()
            ->assertSee('value="1" selected', false)
            ->assertSee('Rp700.000');
        $this->get(route('pumk-admin.umkm-binaan-bri.index', [
            'bulan' => 1,
            'tahun' => 2026,
            'wilayah' => 'Madiun',
            'sektor' => 'Perdagangan',
            'kolektibilitas' => 'lancar',
        ]))->assertOk()->assertSee('Mitra Snapshot Bulanan');

        $mitra = PumkBriMitra::firstOrFail();
        $this->get(route('pumk-admin.umkm-binaan-bri.snapshot.show', [
            'mitra' => $mitra->id,
            'bulan' => 1,
            'tahun' => 2026,
        ]))->assertOk()
            ->assertSee('Februari 2026')
            ->assertSee('Rp650.000');
    }

    public function test_xls_upload_is_supported_and_template_contains_all_17_columns(): void
    {
        $this->get(route('pumk-admin.umkm-binaan-bri.template'))
            ->assertOk()
            ->assertDownload('template-umkm-binaan-via-bri.xlsx');

        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            \App\Services\Pumk\PumkBriMonthlySnapshotImportService::TEMPLATE_HEADERS,
            [
                '001', 'BRI Unit Madiun', 'Mitra XLS', 'Jl. Mawar', 'Jawa Timur',
                'Kota Madiun', 'Perdagangan', '36 Bulan', '15/01/2026',
                '15/01/2029', '1000000', '750000', 'Lancar', '',
                'Kuliner', '10000', '5000',
            ],
        ], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'pumk-bri-xls-');
        $this->assertNotFalse($path);

        try {
            (new Xls($spreadsheet))->save($path);
            $upload = UploadedFile::fake()->createWithContent('laporan-bri.xls', file_get_contents($path));
        } finally {
            $spreadsheet->disconnectWorksheets();
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->post(route('pumk-admin.umkm-binaan-bri.import'), [
            'bulan' => 1,
            'tahun' => 2026,
            'import_file' => $upload,
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.create'))
            ->assertSessionHas('import_result.status', 'imported');

        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', [
            'nama_mitra_binaan' => 'Mitra XLS',
            'jumlah_realisasi' => 1000000,
            'outstanding' => 750000,
        ]);
    }

    public function test_xlsx_upload_is_read_using_the_17_column_template(): void
    {
        $spreadsheet = new Spreadsheet;
        $spreadsheet->getActiveSheet()->fromArray([
            \App\Services\Pumk\PumkBriMonthlySnapshotImportService::TEMPLATE_HEADERS,
            [
                '002', 'BRI Unit Ngawi', 'Mitra XLSX', 'Jl. Melati', 'Jawa Timur',
                'Ngawi', 'Industri', '24 Bulan', '01/02/2026', '01/02/2028',
                '2500000', '2000000', 'Kurang Lancar', '', 'Kerajinan', '25000', '12000',
            ],
        ], null, 'A1');
        $path = tempnam(sys_get_temp_dir(), 'pumk-bri-xlsx-');
        $this->assertNotFalse($path);

        try {
            (new Xlsx($spreadsheet))->save($path);
            $upload = UploadedFile::fake()->createWithContent('laporan-bri.xlsx', file_get_contents($path));
        } finally {
            $spreadsheet->disconnectWorksheets();
            if (is_file($path)) {
                unlink($path);
            }
        }

        $this->post(route('pumk-admin.umkm-binaan-bri.import'), [
            'bulan' => 2,
            'tahun' => 2026,
            'import_file' => $upload,
        ])->assertRedirect(route('pumk-admin.umkm-binaan-bri.create'))
            ->assertSessionHas('import_result.status', 'imported');

        $this->assertDatabaseHas('pumk_bri_snapshot_bulanan', [
            'nama_mitra_binaan' => 'Mitra XLSX',
            'bri_unit' => 'BRI Unit Ngawi',
            'outstanding' => 2000000,
            'kolektibilitas_bumn' => 'Kurang Lancar',
        ]);
    }

    private function uploadCsv(int $month, int $year, int $outstanding): \Illuminate\Testing\TestResponse
    {
        $csv = implode("\n", [
            implode(',', \App\Services\Pumk\PumkBriMonthlySnapshotImportService::TEMPLATE_HEADERS),
            implode(',', [
                '001',
                'BRI Unit Madiun',
                'Mitra Snapshot Bulanan',
                'Alamat usaha',
                'Jawa Timur',
                'Kota Madiun',
                'Perdagangan',
                '36 Bulan',
                '15/01/2026',
                '15/01/2029',
                '1.000.000',
                number_format($outstanding, 0, ',', '.'),
                'Lancar',
                '',
                'Kuliner',
                '10.000',
                '5.000',
            ]),
        ]);

        return $this->post(route('pumk-admin.umkm-binaan-bri.import'), [
            'bulan' => $month,
            'tahun' => $year,
            'import_file' => UploadedFile::fake()->createWithContent('snapshot-bri.csv', $csv),
        ]);
    }
}
