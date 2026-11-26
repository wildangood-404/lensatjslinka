<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_mitra', function (Blueprint $table): void {
            $table->string('pasfoto', 500)->nullable();
            $table->text('nik')->nullable();
            $table->string('jenis_kelamin', 20)->nullable();
            $table->string('tempat_lahir', 150)->nullable();
            $table->date('tanggal_lahir')->nullable();
            $table->string('email')->nullable();

            foreach (['ktp', 'domisili', 'usaha'] as $address) {
                $table->string("alamat_{$address}_provinsi")->nullable();
                $table->string("alamat_{$address}_kabupaten_kota")->nullable();
                $table->string("alamat_{$address}_kecamatan")->nullable();
                $table->string("alamat_{$address}_desa")->nullable();
                $table->string("alamat_{$address}_rt", 10)->nullable();
                $table->string("alamat_{$address}_rw", 10)->nullable();
                $table->text("alamat_{$address}_lengkap")->nullable();
                $table->string("alamat_{$address}_share_lok", 2048)->nullable();
            }

            $table->boolean('alamat_domisili_sama_ktp')->nullable()->default(true);
            $table->boolean('alamat_usaha_sama_ktp')->nullable()->default(true);
        });

        Schema::create('pumk_mitra_dokumen', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained('pumk_mitra')->cascadeOnDelete();
            $table->string('file_path', 500);
            $table->string('nama_file_asli', 255);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('file_size');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('uploaded_at');
            $table->timestamps();
        });

        Schema::table('pumk_pinjaman', function (Blueprint $table): void {
            $table->decimal('denda', 18, 2)->nullable();
            $table->string('status_pinjaman', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pumk_pinjaman', function (Blueprint $table): void {
            $table->dropColumn(['denda', 'status_pinjaman']);
        });
        Schema::dropIfExists('pumk_mitra_dokumen');
        Schema::table('pumk_mitra', function (Blueprint $table): void {
            $table->dropColumn([
                'pasfoto', 'nik', 'jenis_kelamin', 'tempat_lahir', 'tanggal_lahir', 'email',
                'alamat_ktp_provinsi', 'alamat_ktp_kabupaten_kota', 'alamat_ktp_kecamatan', 'alamat_ktp_desa',
                'alamat_ktp_rt', 'alamat_ktp_rw', 'alamat_ktp_lengkap', 'alamat_ktp_share_lok',
                'alamat_domisili_provinsi', 'alamat_domisili_kabupaten_kota', 'alamat_domisili_kecamatan', 'alamat_domisili_desa',
                'alamat_domisili_rt', 'alamat_domisili_rw', 'alamat_domisili_lengkap', 'alamat_domisili_share_lok',
                'alamat_usaha_provinsi', 'alamat_usaha_kabupaten_kota', 'alamat_usaha_kecamatan', 'alamat_usaha_desa',
                'alamat_usaha_rt', 'alamat_usaha_rw', 'alamat_usaha_lengkap', 'alamat_usaha_share_lok',
                'alamat_domisili_sama_ktp', 'alamat_usaha_sama_ktp',
            ]);
        });
    }
};
