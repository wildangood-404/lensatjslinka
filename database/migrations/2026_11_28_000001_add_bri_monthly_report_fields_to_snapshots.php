<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_bri_snapshot_bulanan', function (Blueprint $table): void {
            $table->string('kode_unit')->nullable();
            $table->string('bri_unit')->nullable();
            $table->string('nama_mitra_binaan')->nullable();
            $table->text('alamat')->nullable();
            $table->string('provinsi')->nullable();
            $table->string('kabupaten_kota')->nullable();
            $table->string('sektor_ekonomi_bumn')->nullable();
            $table->string('jangka_waktu')->nullable();
            $table->date('tanggal_realisasi')->nullable();
            $table->date('tanggal_jatuh_tempo')->nullable();
            $table->decimal('jumlah_realisasi', 18, 2)->nullable();
            $table->decimal('outstanding', 18, 2)->nullable();
            $table->string('kolektibilitas_bumn')->nullable();
            $table->date('tanggal_lunas')->nullable();
            $table->string('jenis_usaha')->nullable();
            $table->decimal('accrued_interest', 18, 2)->nullable();
            $table->decimal('angsuran_bunga_diterima_giro', 18, 2)->nullable();
        });

        DB::table('pumk_bri_snapshot_bulanan')
            ->whereNull('nama_mitra_binaan')
            ->update([
                'nama_mitra_binaan' => DB::raw('nama_mitra_sumber'),
                'alamat' => DB::raw('alamat_sumber'),
                'kabupaten_kota' => DB::raw('wilayah_sumber'),
                'sektor_ekonomi_bumn' => DB::raw('sektor_usaha_sumber'),
                'jangka_waktu' => DB::raw('tenor_sumber'),
                'tanggal_realisasi' => DB::raw('tanggal_pencairan_sumber'),
                'tanggal_jatuh_tempo' => DB::raw('tanggal_jatuh_tempo_sumber'),
                'jumlah_realisasi' => DB::raw('pinjaman_sumber'),
                'outstanding' => DB::raw('saldo_piutang'),
                'kolektibilitas_bumn' => DB::raw('COALESCE(kolektibilitas_label, kolektibilitas_kode)'),
            ]);
    }

    public function down(): void
    {
        Schema::table('pumk_bri_snapshot_bulanan', function (Blueprint $table): void {
            $table->dropColumn([
                'kode_unit',
                'bri_unit',
                'nama_mitra_binaan',
                'alamat',
                'provinsi',
                'kabupaten_kota',
                'sektor_ekonomi_bumn',
                'jangka_waktu',
                'tanggal_realisasi',
                'tanggal_jatuh_tempo',
                'jumlah_realisasi',
                'outstanding',
                'kolektibilitas_bumn',
                'tanggal_lunas',
                'jenis_usaha',
                'accrued_interest',
                'angsuran_bunga_diterima_giro',
            ]);
        });
    }
};
