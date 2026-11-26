<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('umkm_monitorings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('mitra_id')->constrained('pumk_mitra')->cascadeOnDelete();
            $table->unsignedSmallInteger('periode_tahun');
            $table->string('jenis_kunjungan', 120);
            $table->text('tindak_lanjut');
            $table->text('keterangan')->nullable();
            $table->json('dokumentasi')->nullable();
            $table->timestamps();

            $table->index(['mitra_id', 'periode_tahun']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('umkm_monitorings');
    }
};
