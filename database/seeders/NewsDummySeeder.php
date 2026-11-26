<?php

namespace Database\Seeders;

use App\Models\NewsItem;
use Illuminate\Database\Seeder;

class NewsDummySeeder extends Seeder
{
    public function run(): void
    {
        $newsItems = [
            [
                'title' => 'Penyaluran Bantuan Pompa Air & Sosialisasi Konservasi Air Bersih di Banyuwangi',
                'caption' => 'PT INKA (Persero) melalui program TJSL Pilar Lingkungan menyalurkan bantuan fasilitas pompa air bersih dan edukasi konservasi bagi siswa sekolah dasar di wilayah Ketapang, Banyuwangi.',
                'content' => "PT INKA (Persero) menyalurkan bantuan fasilitas pompa air bersih kepada sekolah dasar di wilayah Ketapang, Banyuwangi. Dukungan ini diharapkan membantu sekolah memenuhi kebutuhan air bersih untuk kegiatan belajar dan sanitasi sehari-hari.\n\nKegiatan dilengkapi sosialisasi konservasi air bersih bagi siswa dan warga sekolah. Peserta diajak memahami cara menggunakan air secara bijak, menjaga sumber air, serta membangun kebiasaan sederhana untuk merawat lingkungan.",
                'source' => 'Lingkungan',
                'published_date' => '18 September 2026',
            ],
            [
                'title' => 'Pemberdayaan UMKM Binaan Madiun Raya Melalui Pendampingan Digital & Akses Permodalan PUMK',
                'caption' => 'Sebanyak puluhan pelaku UMKM binaan di wilayah Madiun dan Magetan mengikuti pelatihan pemasaran digital serta penguatan manajemen keuangan guna meningkatkan daya saing produk lokal.',
                'content' => "Pelaku UMKM binaan di wilayah Madiun Raya mengikuti pendampingan yang memadukan pemasaran digital, pengelolaan keuangan sederhana, dan penguatan kualitas produk. Peserta mempraktikkan pembuatan katalog digital, penyusunan informasi produk, serta pencatatan transaksi harian.\n\nPendampingan ini menjadi bagian dari upaya PT INKA (Persero) membuka akses pengembangan usaha dan permodalan PUMK secara berkelanjutan. UMKM juga memperoleh kesempatan untuk berkonsultasi mengenai tantangan pemasaran dan rencana pengembangan usaha.",
                'source' => 'PUMK',
                'published_date' => '12 Oktober 2026',
            ],
            [
                'title' => 'Sinergi Penyaluran Dana Bergulir PUMK Bersama BRI untuk Penguatan Sektor Industri Kreatif',
                'caption' => 'Kolaborasi strategis PT INKA (Persero) dan BRI memperluas jangkauan pembiayaan usaha mikro dan kecil pada sektor konveksi, meubel, dan pengolahan pangan.',
                'content' => "PT INKA (Persero) dan BRI memperkuat sinergi penyaluran dana bergulir bagi usaha mikro dan kecil. Kerja sama ini mendukung akses pembiayaan yang lebih luas bagi pelaku usaha di sektor konveksi, meubel, dan pengolahan pangan.\n\nSelain pembiayaan, mitra binaan didorong untuk mengelola modal secara produktif, menjaga kualitas produk, dan menyusun rencana usaha yang terukur. Kolaborasi ini diharapkan memperkuat rantai usaha lokal sekaligus mendorong pertumbuhan ekonomi masyarakat.",
                'source' => 'Kemitraan PUMK',
                'published_date' => '3 November 2026',
            ],
            [
                'title' => 'Dukungan Sarana Prasarana Pendidikan & Pelatihan Vokasi Berbasis Teknologi Perkeretaapian',
                'caption' => 'Komitmen PT INKA (Persero) dalam meningkatkan kualitas SDM unggul melalui bantuan perangkat praktik dan pengenalan teknologi manufaktur perkeretaapian.',
                'content' => "PT INKA (Persero) mendukung pembelajaran vokasi melalui bantuan perangkat praktik dan sesi pengenalan teknologi manufaktur perkeretaapian. Dukungan sarana ini memberi kesempatan kepada peserta didik untuk mengenal peralatan dan proses kerja industri secara lebih dekat.\n\nMelalui kolaborasi dengan satuan pendidikan, peserta juga memperoleh wawasan tentang keselamatan kerja, kedisiplinan, dan keterampilan teknis yang dibutuhkan di dunia industri. Program diharapkan membantu menyiapkan sumber daya manusia yang adaptif dan siap berkontribusi.",
                'source' => 'Pendidikan',
                'published_date' => '10 Oktober 2026',
            ],
            [
                'title' => 'Program Penghijauan & Pengelolaan Sampah Terpadu di Lingkungan Sekitar Perusahaan',
                'caption' => 'Aksi nyata pelestarian lingkungan melalui penanaman pohon produktif dan fasilitasi bank sampah masyarakat demi mewujudkan ekosistem hijau berkelanjutan.',
                'content' => "PT INKA (Persero) bersama masyarakat melaksanakan kegiatan penghijauan melalui penanaman pohon produktif di lingkungan sekitar perusahaan. Kegiatan ini menjadi langkah bersama untuk menambah ruang hijau dan memperkuat kepedulian terhadap kelestarian lingkungan.\n\nProgram juga mendorong pengelolaan sampah terpadu melalui fasilitasi bank sampah. Warga diajak memilah sampah dari rumah, memanfaatkan material yang masih bernilai, dan menjaga kebersihan lingkungan secara berkelanjutan.",
                'source' => 'Lingkungan',
                'published_date' => '20 November 2026',
            ],
        ];

        foreach ($newsItems as $newsItem) {
            NewsItem::query()->updateOrCreate(
                ['title' => $newsItem['title']],
                [
                    ...$newsItem,
                    'image_path' => 'images/news/tjsl-placeholder.svg',
                    'status' => 'published',
                ],
            );
        }
    }
}
