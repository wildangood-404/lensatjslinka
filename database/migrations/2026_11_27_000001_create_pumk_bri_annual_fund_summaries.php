<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pumk_bri_annual_fund_summaries', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('tahun')->unique();
            $table->decimal('total_dana_ditransfer', 20, 0)->default(0);
            $table->decimal('angsuran_pokok', 20, 0)->default(0);
            $table->decimal('jasmin', 20, 0)->default(0);
            $table->decimal('penyaluran', 20, 0)->default(0);
            $table->decimal('os', 20, 0)->default(0);
            $table->decimal('sisa_dana_akhir', 22, 0)->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pumk_bri_annual_fund_summaries');
    }
};
