<?php

namespace Tests\Feature;

use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\User;
use App\Services\Monitoring\MonitoringXlsxReader;
use App\Services\Pumk\PiutangCalculator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Phar;
use PharData;
use Tests\TestCase;

class PumkUmkmBinaanInputTest extends TestCase
{
    use RefreshDatabase;

    public function test_template_download_contains_the_required_headers(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');

        $response = $this->get(route('pumk-admin.umkm-binaan.template'))
            ->assertOk()
            ->assertDownload('template-umkm-binaan.xlsx');
        $path = tempnam(sys_get_temp_dir(), 'umkm-binaan-test-');
        $this->assertNotFalse($path);
        file_put_contents($path, $response->streamedContent());

        try {
            $workbook = app(MonitoringXlsxReader::class)->read($path, ['UMKM Binaan']);
            $this->assertSame([
                'umkm_binaan',
                'alamat',
                'wilayah',
                'pemilik',
                'kontak',
                'sektor',
                'pencairan',
                'tahun',
                'kolektibilitas',
                'sisa_pinjaman_(pokok_&_bunga)',
                'sisa_pinjaman_pokok',
                'sisa_pinjaman_bunga',
                'denda',
                'status',
            ], $workbook['UMKM Binaan']['headers']);
        } finally {
            unlink($path);
        }
    }

    public function test_csv_import_saves_profile_region_and_snapshot_and_skips_duplicate_rows(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');
        $csv = implode("\n", [
            'UMKM Binaan,Alamat,Wilayah,Pemilik,Kontak,Sektor,Pencairan,Tahun,Kolektibilitas,Sisa Pinjaman (Pokok & Bunga),Sisa Pinjaman Pokok,Sisa Pinjaman Bunga,Denda,Status',
            'Toko Uji,Alamat usaha,Kota Madiun,Pemilik Uji,081234567890,Jasa,1.000.000,2026,Lancar,750.000,500.000,250.000,0,Aktif',
            'Toko Uji,Alamat duplikat,Kota Lain,Pemilik Duplikat,,Jasa,100.000,2025,Macet,100.000,50.000,50.000,0,Aktif',
            ',Alamat gagal,Kota Madiun,,,,,,,,,,,,',
        ]);

        $this->followingRedirects()->post(route('pumk-admin.umkm-binaan.import'), [
            'import_file' => UploadedFile::fake()->createWithContent('umkm-binaan.csv', $csv),
        ])->assertOk()
            ->assertSee('Impor selesai: 1 berhasil, 1 duplikat dilewati, 1 gagal.')
            ->assertSee('Nama UMKM binaan sudah terdaftar; baris dilewati.');

        $mitra = PumkMitra::query()->where('nama_mitra', 'Toko Uji')->firstOrFail();
        $this->assertSame('Kota Madiun', $mitra->kabupaten_kota);
        $this->assertSame('Alamat usaha', $mitra->alamat_ktp_lengkap);
        $this->assertSame('Pemilik Uji', $mitra->nama_pemilik);
        $this->assertSame('081234567890', $mitra->no_telepon_encrypted);

        $loan = PumkPinjaman::query()->where('mitra_id', $mitra->id)->firstOrFail();
        $this->assertSame('2026', (string) $loan->tahun_pencairan);
        $this->assertSame('500000.00', $loan->sisa_pokok);
        $this->assertSame('250000.00', $loan->sisa_bunga);
        $this->assertSame('750000.00', $loan->total_sisa);
        $this->assertTrue($loan->baseline_sumber['manual_snapshot']);
        $calculated = app(PiutangCalculator::class)->hitungUntukPinjaman($loan);
        $this->assertSame('500000.00', $calculated['sisa_pokok']);
        $this->assertSame('250000.00', $calculated['sisa_bunga']);
        $this->assertSame('750000.00', $calculated['total_sisa']);
        $this->assertSame('lancar', $calculated['kolektibilitas']);
        $this->assertSame(1, PumkMitra::query()->count());
    }

    public function test_xlsx_import_reads_template_columns_and_saves_rows(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');
        $xlsx = $this->xlsxContent([[
            'UMKM Excel',
            'Alamat Excel',
            'Kabupaten Ngawi',
            'Pemilik Excel',
            '081200000000',
            'Perdagangan',
            '2500000',
            '2025',
            'Bermasalah',
            '1500000',
            '1000000',
            '500000',
            '0',
            'Aktif',
        ]]);

        $this->post(route('pumk-admin.umkm-binaan.import'), [
            'import_file' => UploadedFile::fake()->createWithContent('umkm-binaan.xlsx', $xlsx),
        ])->assertRedirect(route('pumk-admin.mitra.create'))
            ->assertSessionHas('import_result.imported', 1)
            ->assertSessionHas('import_result.failed', 0);

        $mitra = PumkMitra::query()->where('nama_mitra', 'UMKM Excel')->firstOrFail();
        $this->assertSame('Kabupaten Ngawi', $mitra->kabupaten_kota);
        $this->assertSame('Perdagangan', $mitra->sektor_sumber);
        $this->assertSame('bermasalah', $mitra->pinjaman()->firstOrFail()->kolektibilitas);
        $this->get(route('pumk-admin.umkm-binaan.index', ['kolektibilitas' => 'bermasalah']))
            ->assertOk()
            ->assertSee('UMKM Excel')
            ->assertSee('value="bermasalah" selected', false);
    }

    public function test_manual_create_form_exposes_profile_fields_and_import_tabs(): void
    {
        $this->actingAs($this->pumkAdmin(), 'pumk');

        $this->get(route('pumk-admin.mitra.create'))
            ->assertOk()
            ->assertSee('Input Manual')
            ->assertSee('Import Excel (Input Massal)')
            ->assertSee('Unggah Pasfoto')
            ->assertSee('JPG/PNG, maks. 5 MB')
            ->assertSee('Alamat domisili sama dengan alamat KTP')
            ->assertSee('Alamat usaha sama dengan alamat KTP')
            ->assertSee('Download Template Excel (.xlsx)')
            ->assertSee('Bermasalah')
            ->assertSee('Sisa Pinjaman Total (Pokok &amp; Bunga - otomatis)', false);
    }

    public function test_manual_create_saves_distinct_addresses_and_supporting_documents(): void
    {
        Storage::fake('local');
        $this->actingAs($this->pumkAdmin(), 'pumk');

        $response = $this->post(route('pumk-admin.mitra.store'), [
            'nama_mitra' => 'UMKM Alamat Lengkap',
            'nama_pemilik' => 'Pemilik Uji',
            'nik' => '1234567890123456',
            'jenis_kelamin' => 'Perempuan',
            'tempat_lahir' => 'Madiun',
            'tanggal_lahir' => '1990-01-02',
            'no_telepon' => '081234567890',
            'email' => 'pemilik@example.test',
            'alamat_ktp_provinsi' => 'Jawa Timur',
            'alamat_ktp_kabupaten_kota' => 'Kota Madiun',
            'alamat_ktp_kecamatan' => 'Manguharjo',
            'alamat_ktp_desa' => 'Pangongangan',
            'alamat_ktp_rt' => '01',
            'alamat_ktp_rw' => '02',
            'alamat_ktp_lengkap' => 'Jl. KTP No. 1',
            'alamat_ktp_share_lok' => '-7.63, 111.52',
            'alamat_domisili_berbeda_ktp' => '1',
            'alamat_domisili_provinsi' => 'Jawa Timur',
            'alamat_domisili_kabupaten_kota' => 'Kabupaten Madiun',
            'alamat_domisili_kecamatan' => 'Wungu',
            'alamat_domisili_desa' => 'Mojopurno',
            'alamat_domisili_lengkap' => 'Jl. Domisili No. 2',
            'alamat_usaha_sama_ktp' => '1',
            'sektor_usaha' => 'Jasa',
            'financial_snapshot' => '1',
            'sisa_pokok' => '100000',
            'sisa_bunga' => '25000',
            'total_sisa' => '125000',
            'kolektibilitas' => 'bermasalah',
            'status_pinjaman' => 'aktif',
            'dokumen_pendukung' => [
                UploadedFile::fake()->createWithContent('dokumen-uji.txt', 'dokumen pendukung'),
            ],
        ]);

        $mitra = PumkMitra::query()->where('nama_mitra', 'UMKM Alamat Lengkap')->firstOrFail();
        $response->assertRedirect(route('pumk-admin.umkm-binaan.show', $mitra));
        $this->assertSame('Kota Madiun', $mitra->kabupaten_kota);
        $this->assertSame('Kabupaten Madiun', $mitra->alamat_domisili_kabupaten_kota);
        $this->assertSame('Kota Madiun', $mitra->alamat_usaha_kabupaten_kota);
        $this->assertFalse($mitra->alamat_domisili_sama_ktp);
        $this->assertTrue($mitra->alamat_usaha_sama_ktp);
        $this->assertDatabaseHas('pumk_pinjaman', [
            'mitra_id' => $mitra->id,
            'sisa_pokok' => 100000,
            'sisa_bunga' => 25000,
            'total_sisa' => 125000,
            'kolektibilitas' => 'bermasalah',
        ]);
        $document = $mitra->dokumenPendukung()->firstOrFail();
        Storage::disk('local')->assertExists($document->file_path);
        $this->get(route('pumk-admin.umkm-binaan.documents.download', [$mitra, $document]))
            ->assertOk()
            ->assertDownload('dokumen-uji.txt');
        $this->get(route('pumk-admin.umkm-binaan.show', $mitra))
            ->assertOk()
            ->assertSee('UMKM Alamat Lengkap')
            ->assertSee('Pemilik Uji')
            ->assertSee('Kabupaten Madiun')
            ->assertSee('Bermasalah')
            ->assertSee('umkm-status-badge is-critical', false);
        $this->get(route('pumk-admin.umkm-binaan.index', ['kolektibilitas' => 'bermasalah']))
            ->assertOk()
            ->assertSee('UMKM Alamat Lengkap');
        $this->get(route('pumk-admin.mitra.edit', [
            $mitra,
            'pinjaman' => $mitra->pinjaman()->value('id'),
            'return_to_profile' => 1,
        ]))
            ->assertOk()
            ->assertSee('Edit UMKM Binaan')
            ->assertSee('name="return_to_profile" value="1"', false)
            ->assertSee('Jl. KTP No. 1');
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

    /** @param list<list<string>> $rows */
    private function xlsxContent(array $rows): string
    {
        $headers = [
            'UMKM Binaan', 'Alamat', 'Wilayah', 'Pemilik', 'Kontak', 'Sektor', 'Pencairan',
            'Tahun', 'Kolektibilitas', 'Sisa Pinjaman (Pokok & Bunga)',
            'Sisa Pinjaman Pokok', 'Sisa Pinjaman Bunga', 'Denda', 'Status',
        ];
        $sheetRows = [];
        foreach (array_merge([$headers], $rows) as $rowIndex => $rowValues) {
            $cells = '';
            foreach ($rowValues as $columnIndex => $value) {
                $reference = $this->columnName($columnIndex + 1).($rowIndex + 1);
                $cells .= '<c r="'.$reference.'" t="inlineStr"><is><t>'
                    .htmlspecialchars($value, ENT_QUOTES | ENT_XML1, 'UTF-8')
                    .'</t></is></c>';
            }
            $sheetRows[] = '<row r="'.($rowIndex + 1).'">'.$cells.'</row>';
        }
        $path = sys_get_temp_dir().DIRECTORY_SEPARATOR.'umkm-import-'.uniqid('', true).'.zip';
        $archive = new PharData($path, 0, null, Phar::ZIP);
        $archive->addFromString('[Content_Types].xml', '<Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/><Override PartName="/xl/worksheets/sheet1.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/></Types>');
        $archive->addFromString('_rels/.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/></Relationships>');
        $archive->addFromString('xl/workbook.xml', '<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships"><sheets><sheet name="UMKM Binaan" sheetId="1" r:id="rId1"/></sheets></workbook>');
        $archive->addFromString('xl/_rels/workbook.xml.rels', '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet1.xml"/></Relationships>');
        $archive->addFromString('xl/worksheets/sheet1.xml', '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main"><sheetData>'.implode('', $sheetRows).'</sheetData></worksheet>');
        unset($archive);
        $contents = file_get_contents($path);
        unlink($path);

        return $contents ?: '';
    }

    private function columnName(int $number): string
    {
        $name = '';
        while ($number > 0) {
            $number--;
            $name = chr(65 + ($number % 26)).$name;
            $number = intdiv($number, 26);
        }

        return $name;
    }
}
