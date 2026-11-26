<?php

namespace Database\Seeders;

use App\Models\PumkBriPeriod;
use App\Models\PumkBriRealization;
use App\Models\PumkLoanClosure;
use App\Models\PumkMitra;
use App\Models\PumkPinjaman;
use App\Models\PumkSektorUsaha;
use App\Models\PumkWilayah;
use App\Services\Pumk\PumkLoanSnapshotService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class PumkBriDummySeeder extends Seeder
{
    private const LUNAS_AT = '2026-01-15 12:00:00';

    /**
     * @var array<int, array<string, string|int>>
     */
    private const PERIODS = [
        ['periode_tahun' => 2025, 'anggaran_rka' => 1500000000],
        ['periode_tahun' => 2026, 'anggaran_rka' => 2000000000],
    ];

    /**
     * @var array<int, array<string, string|int|null>>
     */
    private const REALIZATIONS = [
        [
            'periode_tahun' => 2025,
            'uraian' => 'Penyaluran PUMK Tahap I Wilayah Madiun Raya via BRI',
            'waktu' => '2025-03-18',
            'pic' => 'Rendra Kurniawan',
            'nominal' => 650000000,
            'dokumen_path' => 'pumk-bri/realisasi-2025-t1.pdf',
            'dokumen_nama' => 'Realisasi PUMK BRI 2025 Tahap I (Dummy).pdf',
        ],
        [
            'periode_tahun' => 2025,
            'uraian' => 'Penyaluran PUMK Tahap II Sektor Industri & Perdagangan via BRI',
            'waktu' => '2025-08-22',
            'pic' => 'Sari Wulandari',
            'nominal' => 720000000,
            'dokumen_path' => 'pumk-bri/realisasi-2025-t2.pdf',
            'dokumen_nama' => 'Realisasi PUMK BRI 2025 Tahap II (Dummy).pdf',
        ],
        [
            'periode_tahun' => 2026,
            'uraian' => 'Penyaluran Dana Bergulir PUMK Triwulan I via BRI Cabang Madiun',
            'waktu' => '2026-02-14',
            'pic' => 'Rendra Kurniawan',
            'nominal' => 850000000,
            'dokumen_path' => 'pumk-bri/realisasi-2026-tw1.pdf',
            'dokumen_nama' => 'Realisasi PUMK BRI 2026 Triwulan I (Dummy).pdf',
        ],
        [
            'periode_tahun' => 2026,
            'uraian' => 'Penyaluran Dana Bergulir PUMK Triwulan II Wilayah Ngawi & Magetan',
            'waktu' => '2026-06-10',
            'pic' => 'Dwi Prasetyo',
            'nominal' => 550000000,
            'dokumen_path' => null,
            'dokumen_nama' => null,
        ],
    ];

    /**
     * @var array<int, array<string, string|int>>
     */
    private const UMKM = [
        [
            'nama' => 'CV Sumber Rejeki Pangan',
            'pemilik' => 'Haryanto',
            'wilayah' => 'Kota Madiun',
            'sektor' => 'Industri - Makanan Minuman',
            'pokok' => 60000000,
            'tahun' => 2025,
            'tanggal_pencairan' => '2025-02-20',
            'kolektibilitas' => 'lancar',
            'sisa_pokok' => 36000000,
            'sisa_bunga' => 1800000,
            'total_sisa' => 37800000,
            'denda' => 0,
            'status_pinjaman' => 'aktif',
            'nik' => '9910000000000001',
            'jenis_kelamin' => 'Laki-laki',
            'tempat_lahir' => 'Madiun',
            'tanggal_lahir' => '1982-03-14',
            'telepon' => '081300001001',
            'email' => 'haryanto.sumberrejeki@example.test',
            'spj' => 'SPJ/BRI/2025/001',
            'kecamatan' => 'Kartoharjo',
            'desa' => 'Kanigoro',
            'rt' => '01',
            'rw' => '02',
            'alamat_ktp' => 'Jl. Cokroaminoto No. 14',
            'alamat_domisili' => 'Jl. Cokroaminoto No. 14',
            'alamat_usaha' => 'Jl. Pahlawan No. 27',
        ],
        [
            'nama' => 'Bengkel Bubut & Konstruksi Baja Putra',
            'pemilik' => 'Wahyu Nugroho',
            'wilayah' => 'Kab. Madiun',
            'sektor' => 'Industri - Bengkel / Pertukangan',
            'pokok' => 85000000,
            'tahun' => 2025,
            'tanggal_pencairan' => '2025-04-15',
            'kolektibilitas' => 'lancar',
            'sisa_pokok' => 51000000,
            'sisa_bunga' => 2550000,
            'total_sisa' => 53550000,
            'denda' => 0,
            'status_pinjaman' => 'aktif',
            'nik' => '9910000000000002',
            'jenis_kelamin' => 'Laki-laki',
            'tempat_lahir' => 'Caruban',
            'tanggal_lahir' => '1978-07-22',
            'telepon' => '081300001002',
            'email' => 'wahyu.nugroho@example.test',
            'spj' => 'SPJ/BRI/2025/002',
            'kecamatan' => 'Mejayan',
            'desa' => 'Krajan',
            'rt' => '02',
            'rw' => '03',
            'alamat_ktp' => 'Jl. Panglima Sudirman No. 32',
            'alamat_domisili' => 'Jl. Panglima Sudirman No. 32',
            'alamat_usaha' => 'Jl. Raya Caruban No. 65',
        ],
        [
            'nama' => 'Konveksi Seragam & Bordir Mandiri',
            'pemilik' => 'Endang Sulastri',
            'wilayah' => 'Kab. Magetan',
            'sektor' => 'Industri - Konveksi',
            'pokok' => 50000000,
            'tahun' => 2025,
            'tanggal_pencairan' => '2025-05-01',
            'kolektibilitas' => 'kurang_lancar',
            'sisa_pokok' => 35000000,
            'sisa_bunga' => 1750000,
            'total_sisa' => 36750000,
            'denda' => 400000,
            'status_pinjaman' => 'aktif',
            'nik' => '9910000000000003',
            'jenis_kelamin' => 'Perempuan',
            'tempat_lahir' => 'Magetan',
            'tanggal_lahir' => '1985-11-08',
            'telepon' => '081300001003',
            'email' => 'endang.sulastri@example.test',
            'spj' => 'SPJ/BRI/2025/003',
            'kecamatan' => 'Maospati',
            'desa' => 'Kraton',
            'rt' => '03',
            'rw' => '01',
            'alamat_ktp' => 'Jl. Raya Maospati No. 18',
            'alamat_domisili' => 'Dusun Kraton RT 03/RW 01',
            'alamat_usaha' => 'Pasar Maospati Blok B-4',
        ],
        [
            'nama' => 'UD Kayu Jati & Kusen Makmur',
            'pemilik' => 'Bambang Sutrisno',
            'wilayah' => 'Kab. Ngawi',
            'sektor' => 'Industri - Meubel',
            'pokok' => 90000000,
            'tahun' => 2026,
            'tanggal_pencairan' => '2026-01-19',
            'kolektibilitas' => 'lancar',
            'sisa_pokok' => 75000000,
            'sisa_bunga' => 3750000,
            'total_sisa' => 78750000,
            'denda' => 0,
            'status_pinjaman' => 'aktif',
            'nik' => '9910000000000004',
            'jenis_kelamin' => 'Laki-laki',
            'tempat_lahir' => 'Ngawi',
            'tanggal_lahir' => '1974-01-17',
            'telepon' => '081300001004',
            'email' => 'bambang.sutrisno@example.test',
            'spj' => 'SPJ/BRI/2026/004',
            'kecamatan' => 'Ngawi',
            'desa' => 'Margomulyo',
            'rt' => '04',
            'rw' => '02',
            'alamat_ktp' => 'Jl. Trunojoyo No. 22',
            'alamat_domisili' => 'Jl. Trunojoyo No. 22',
            'alamat_usaha' => 'Jl. Ahmad Yani No. 71',
        ],
        [
            'nama' => 'Grosir Sembako & Hasil Bumi Utama',
            'pemilik' => 'Sri Wahyuni',
            'wilayah' => 'Kab. Ponorogo',
            'sektor' => 'Perdagangan',
            'pokok' => 45000000,
            'tahun' => 2025,
            'tanggal_pencairan' => '2025-07-14',
            'kolektibilitas' => 'diragukan',
            'sisa_pokok' => 31500000,
            'sisa_bunga' => 1575000,
            'total_sisa' => 33075000,
            'denda' => 850000,
            'status_pinjaman' => 'reschedule',
            'nik' => '9910000000000005',
            'jenis_kelamin' => 'Perempuan',
            'tempat_lahir' => 'Ponorogo',
            'tanggal_lahir' => '1981-05-30',
            'telepon' => '081300001005',
            'email' => 'sri.wahyuni@example.test',
            'spj' => 'SPJ/BRI/2025/005',
            'kecamatan' => 'Babadan',
            'desa' => 'Japan',
            'rt' => '02',
            'rw' => '04',
            'alamat_ktp' => 'Jl. Raya Babadan No. 16',
            'alamat_domisili' => 'Jl. Raya Babadan No. 16',
            'alamat_usaha' => 'Pasar Ponorogo Kios 12',
        ],
        [
            'nama' => 'Agribisnis Jamur Tiram & Pupuk Organik',
            'pemilik' => 'Mochammad Arifin',
            'wilayah' => 'Kab. Madiun',
            'sektor' => 'Pertanian',
            'pokok' => 35000000,
            'tahun' => 2026,
            'tanggal_pencairan' => '2026-03-20',
            'kolektibilitas' => 'lancar',
            'sisa_pokok' => 28000000,
            'sisa_bunga' => 1400000,
            'total_sisa' => 29400000,
            'denda' => 0,
            'status_pinjaman' => 'aktif',
            'nik' => '9910000000000006',
            'jenis_kelamin' => 'Laki-laki',
            'tempat_lahir' => 'Madiun',
            'tanggal_lahir' => '1990-09-11',
            'telepon' => '081300001006',
            'email' => 'arifin.agribisnis@example.test',
            'spj' => 'SPJ/BRI/2026/006',
            'kecamatan' => 'Wungu',
            'desa' => 'Pilangrejo',
            'rt' => '01',
            'rw' => '05',
            'alamat_ktp' => 'Jl. Raya Wungu No. 19',
            'alamat_domisili' => 'Dusun Pilangrejo RT 01/RW 05',
            'alamat_usaha' => 'Area Pertanian Pilangrejo Blok 3',
        ],
        [
            'nama' => 'Kerajinan Kulit & Sepatu Magetan Jaya',
            'pemilik' => 'Ratna Puspitasari',
            'wilayah' => 'Kab. Magetan',
            'sektor' => 'Industri - Kreatif',
            'pokok' => 70000000,
            'tahun' => 2025,
            'tanggal_pencairan' => '2025-08-03',
            'kolektibilitas' => 'macet',
            'sisa_pokok' => 56000000,
            'sisa_bunga' => 3360000,
            'total_sisa' => 59360000,
            'denda' => 2100000,
            'status_pinjaman' => 'bermasalah',
            'nik' => '9910000000000007',
            'jenis_kelamin' => 'Perempuan',
            'tempat_lahir' => 'Magetan',
            'tanggal_lahir' => '1987-12-03',
            'telepon' => '081300001007',
            'email' => 'ratna.puspitasari@example.test',
            'spj' => 'SPJ/BRI/2025/007',
            'kecamatan' => 'Plaosan',
            'desa' => 'Sarangan',
            'rt' => '05',
            'rw' => '02',
            'alamat_ktp' => 'Jl. Telaga Sarangan No. 10',
            'alamat_domisili' => 'Jl. Telaga Sarangan No. 10',
            'alamat_usaha' => 'Sentra Kerajinan Sarangan Kios 6',
        ],
        [
            'nama' => 'Jasa Bengkel AC & Kelistrikan Mobil',
            'pemilik' => 'Teguh Santoso',
            'wilayah' => 'Kota Madiun',
            'sektor' => 'Jasa',
            'pokok' => 40000000,
            'tahun' => 2024,
            'tanggal_pencairan' => '2024-06-05',
            'kolektibilitas' => 'lancar',
            'sisa_pokok' => 0,
            'sisa_bunga' => 0,
            'total_sisa' => 0,
            'denda' => 0,
            'status_pinjaman' => 'lunas',
            'nik' => '9910000000000008',
            'jenis_kelamin' => 'Laki-laki',
            'tempat_lahir' => 'Madiun',
            'tanggal_lahir' => '1983-06-26',
            'telepon' => '081300001008',
            'email' => 'teguh.santoso@example.test',
            'spj' => 'SPJ/BRI/2024/008',
            'kecamatan' => 'Manguharjo',
            'desa' => 'Manguharjo',
            'rt' => '06',
            'rw' => '03',
            'alamat_ktp' => 'Jl. Sumatera No. 23',
            'alamat_domisili' => 'Jl. Sumatera No. 23',
            'alamat_usaha' => 'Jl. Diponegoro No. 38',
        ],
    ];

    public function run(PumkLoanSnapshotService $snapshots): void
    {
        $disk = Storage::disk('local');
        DB::transaction(function () use ($disk, $snapshots): void {
            $periods = [];
            foreach (self::PERIODS as $periodData) {
                $period = PumkBriPeriod::query()->updateOrCreate(
                    ['periode_tahun' => $periodData['periode_tahun']],
                    ['anggaran_rka' => $periodData['anggaran_rka']],
                );
                $periods[$period->periode_tahun] = $period;
            }

            foreach (self::REALIZATIONS as $record) {
                $period = $periods[$record['periode_tahun']];
                $path = $record['dokumen_path'];
                if ($path !== null && ! $disk->exists($path)
                    && ! $disk->put($path, $this->placeholderPdf((string) $record['uraian']))) {
                    throw new RuntimeException("Dokumen dummy PUMK BRI gagal disimpan: {$path}");
                }

                PumkBriRealization::query()->updateOrCreate(
                    [
                        'pumk_bri_period_id' => $period->id,
                        'uraian' => $record['uraian'],
                    ],
                    [
                        'waktu' => $record['waktu'],
                        'pic' => $record['pic'],
                        'nominal' => $record['nominal'],
                        'dokumen_path' => $path,
                        'dokumen_nama' => $record['dokumen_nama'],
                    ],
                );
            }

            foreach (self::UMKM as $index => $record) {
                $region = PumkWilayah::query()->updateOrCreate(
                    ['slug' => Str::slug(str_replace('.', '', (string) $record['wilayah']))],
                    ['nama' => $record['wilayah'], 'is_active' => true],
                );
                $sectorName = (string) $record['sektor'];
                $sector = PumkSektorUsaha::query()->updateOrCreate(
                    ['slug' => Str::slug(str_replace('/', ' ', $sectorName))],
                    ['nama' => $sectorName, 'is_active' => true],
                );

                $ktpAddress = $this->fullAddress($record, 'alamat_ktp');
                $domicileAddress = $this->fullAddress($record, 'alamat_domisili');
                $businessAddress = $this->fullAddress($record, 'alamat_usaha');
                $mitra = PumkMitra::query()->updateOrCreate(
                    ['source_key' => hash('sha256', 'pumk-bri-dummy-v1|mitra|'.$record['nama'])],
                    [
                        'nama_mitra' => $record['nama'],
                        'kategori_binaan' => 'bri',
                        'nama_pemilik' => $record['pemilik'],
                        'jenis_usaha' => $sectorName,
                        'sektor_usaha_id' => $sector->id,
                        'sektor_sumber' => $sectorName,
                        'wilayah_id' => $region->id,
                        'wilayah_sumber' => $record['wilayah'],
                        'kabupaten_kota' => $record['wilayah'],
                        'provinsi' => 'Jawa Timur',
                        'alamat' => $ktpAddress,
                        'nik' => $record['nik'],
                        'no_ktp_encrypted' => $record['nik'],
                        'jenis_kelamin' => $record['jenis_kelamin'],
                        'tempat_lahir' => $record['tempat_lahir'],
                        'tanggal_lahir' => $record['tanggal_lahir'],
                        'no_telepon_encrypted' => $record['telepon'],
                        'no_rekening_encrypted' => '200000000'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT),
                        'email' => $record['email'],
                        'alamat_ktp_provinsi' => 'Jawa Timur',
                        'alamat_ktp_kabupaten_kota' => $record['wilayah'],
                        'alamat_ktp_kecamatan' => $record['kecamatan'],
                        'alamat_ktp_desa' => $record['desa'],
                        'alamat_ktp_rt' => $record['rt'],
                        'alamat_ktp_rw' => $record['rw'],
                        'alamat_ktp_lengkap' => $ktpAddress,
                        'alamat_domisili_provinsi' => 'Jawa Timur',
                        'alamat_domisili_kabupaten_kota' => $record['wilayah'],
                        'alamat_domisili_kecamatan' => $record['kecamatan'],
                        'alamat_domisili_desa' => $record['desa'],
                        'alamat_domisili_rt' => $record['rt'],
                        'alamat_domisili_rw' => $record['rw'],
                        'alamat_domisili_lengkap' => $domicileAddress,
                        'alamat_domisili_sama_ktp' => $record['alamat_domisili'] === $record['alamat_ktp'],
                        'alamat_usaha_provinsi' => 'Jawa Timur',
                        'alamat_usaha_kabupaten_kota' => $record['wilayah'],
                        'alamat_usaha_kecamatan' => $record['kecamatan'],
                        'alamat_usaha_desa' => $record['desa'],
                        'alamat_usaha_rt' => $record['rt'],
                        'alamat_usaha_rw' => $record['rw'],
                        'alamat_usaha_lengkap' => $businessAddress,
                        'alamat_usaha_sama_ktp' => $record['alamat_usaha'] === $record['alamat_ktp'],
                        'is_active' => $record['status_pinjaman'] !== 'lunas',
                    ],
                );

                $isPaid = $record['status_pinjaman'] === 'lunas';
                $loan = PumkPinjaman::query()->updateOrCreate(
                    ['source_key' => hash('sha256', 'pumk-bri-dummy-v1|pinjaman|'.$record['nama'])],
                    [
                        'mitra_id' => $mitra->id,
                        'spj_awal' => $record['spj'],
                        'tanggal_pencairan' => $record['tanggal_pencairan'],
                        'tahun_pencairan' => $record['tahun'],
                        'pinjaman_pokok' => $record['pokok'],
                        'persen_bunga' => 5,
                        'pinjaman_bunga' => (int) round($record['pokok'] * 0.05),
                        'kolektibilitas' => $record['kolektibilitas'],
                        'status_pinjaman' => $record['status_pinjaman'],
                        'denda' => $record['denda'],
                        'status' => $isPaid ? PumkPinjaman::STATUS_LUNAS : PumkPinjaman::STATUS_AKTIF,
                        'is_active' => ! $isPaid,
                        'lunas_at' => $isPaid ? self::LUNAS_AT : null,
                        'lunas_reason' => $isPaid ? 'normal' : null,
                        'lunas_saldo_pokok' => $isPaid ? 0 : null,
                        'lunas_saldo_bunga' => $isPaid ? 0 : null,
                        'lunas_total_saldo' => $isPaid ? 0 : null,
                        'lunas_tolerance_applied' => $isPaid ? 0 : null,
                    ],
                );

                $snapshots->saveImported($loan, [
                    'sisa_pokok' => $record['sisa_pokok'],
                    'sisa_bunga' => $record['sisa_bunga'],
                    'total_sisa' => $record['total_sisa'],
                    'kolektibilitas' => $record['kolektibilitas'],
                    'denda' => $record['denda'],
                    'status_pinjaman' => $record['status_pinjaman'],
                ]);

                if ($isPaid) {
                    PumkLoanClosure::query()->updateOrCreate(
                        ['pinjaman_id' => $loan->id, 'closed_at' => self::LUNAS_AT],
                        [
                            'closed_by' => null,
                            'settlement_snapshot' => [
                                'lunas_reason' => $loan->lunas_reason,
                                'lunas_saldo_pokok' => $loan->lunas_saldo_pokok,
                                'lunas_saldo_bunga' => $loan->lunas_saldo_bunga,
                                'lunas_total_saldo' => $loan->lunas_total_saldo,
                                'lunas_tolerance_applied' => $loan->lunas_tolerance_applied,
                                'kolektibilitas' => $loan->kolektibilitas,
                            ],
                            'reopened_at' => null,
                            'reopened_by' => null,
                            'reopen_note' => null,
                        ],
                    );
                }
            }
        });

        $this->command?->info('Data dummy PUMK BRI berhasil dibuat atau diperbarui.');
    }

    /**
     * @param  array<string, string|int>  $record
     */
    private function fullAddress(array $record, string $addressField): string
    {
        return implode(', ', [
            $record[$addressField],
            'RT '.$record['rt'].'/RW '.$record['rw'],
            'Desa/Kelurahan '.$record['desa'],
            'Kecamatan '.$record['kecamatan'],
            $record['wilayah'],
            'Jawa Timur',
        ]);
    }

    private function placeholderPdf(string $description): string
    {
        $description = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $description);
        $stream = "BT /F1 14 Tf 50 740 Td (Dokumen dummy PUMK BRI) Tj 0 -24 Td ({$description}) Tj ET";
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 612 792] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
            '<< /Length '.strlen($stream)." >>\nstream\n{$stream}\nendstream",
        ];
        $pdf = "%PDF-1.4\n";
        $offsets = [0];
        foreach ($objects as $index => $object) {
            $offsets[] = strlen($pdf);
            $pdf .= ($index + 1)." 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = strlen($pdf);
        $pdf .= "xref\n0 ".(count($objects) + 1)."\n0000000000 65535 f \n";
        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= sprintf("%010d 00000 n \n", $offset);
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF\n";
    }
}
