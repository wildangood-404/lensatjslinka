<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('pilar_verifikasi')->nullable();
            $table->string('kesesuaian_rka')->nullable();
            $table->string('no_tpb')->nullable();
            $table->string('jenis_program_tpb')->nullable();
            $table->decimal('sisa_anggaran', 18, 2)->nullable();
            $table->text('kebutuhan_program')->nullable();
            $table->date('tanggal_pelaksanaan')->nullable();
            $table->text('keterangan_verifikasi')->nullable();
            $table->json('jenis_bantuan_verifikasi')->nullable();
            $table->string('sumber_pencairan')->nullable();
            $table->string('sumber_pencairan_lainnya')->nullable();
            $table->string('nama_pemilik_rekening')->nullable();
            $table->string('nomor_rekening')->nullable();
            $table->string('nama_bank')->nullable();
            $table->string('jalur_pengadaan_barang')->nullable();
            $table->string('sub_jalur_pengadaan')->nullable();
            $table->string('sumber_barang')->nullable();
            $table->text('deskripsi_barang')->nullable();
            $table->string('kategori_jasa')->nullable();
            $table->text('keterangan_jasa_verifikasi')->nullable();
            $table->json('jalur_pengajuan')->nullable();
            $table->string('status_operasional')->nullable();
            $table->text('catatan_operasional')->nullable();
            $table->string('nama_program_laporan')->nullable();
            $table->decimal('nilai_program_realisasi', 18, 2)->nullable();
            $table->string('no_dokumen_laporan')->nullable();
            $table->string('nama_dokumen_laporan')->nullable();
        });

        Schema::create('program_verification_vendors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('nama_vendor');
            $table->string('rincian_barang');
            $table->decimal('harga', 18, 2)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_verification_vendors');

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'pilar_verifikasi',
                'kesesuaian_rka',
                'no_tpb',
                'jenis_program_tpb',
                'sisa_anggaran',
                'kebutuhan_program',
                'tanggal_pelaksanaan',
                'keterangan_verifikasi',
                'jenis_bantuan_verifikasi',
                'sumber_pencairan',
                'sumber_pencairan_lainnya',
                'nama_pemilik_rekening',
                'nomor_rekening',
                'nama_bank',
                'jalur_pengadaan_barang',
                'sub_jalur_pengadaan',
                'sumber_barang',
                'deskripsi_barang',
                'kategori_jasa',
                'keterangan_jasa_verifikasi',
                'jalur_pengajuan',
                'status_operasional',
                'catatan_operasional',
                'nama_program_laporan',
                'nilai_program_realisasi',
                'no_dokumen_laporan',
                'nama_dokumen_laporan',
            ]);
        });
    }
};
