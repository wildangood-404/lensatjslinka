<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pumk_bri_periods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('periode_tahun')->unique();
            $table->decimal('anggaran_rka', 20, 0)->default(0);
            $table->text('keterangan')->nullable();
            $table->timestamps();
        });

        Schema::create('pumk_bri_realizations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('pumk_bri_period_id')->constrained('pumk_bri_periods')->cascadeOnDelete();
            $table->text('uraian');
            $table->date('waktu');
            $table->string('pic', 255);
            $table->decimal('nominal', 20, 0);
            $table->string('dokumen_path', 500)->nullable();
            $table->string('dokumen_nama', 255)->nullable();
            $table->timestamps();

            $table->index(['pumk_bri_period_id', 'waktu']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pumk_bri_realizations');
        Schema::dropIfExists('pumk_bri_periods');
    }
};
