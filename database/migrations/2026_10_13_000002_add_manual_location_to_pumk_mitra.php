<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_mitra', function (Blueprint $table) {
            $table->string('kabupaten_kota')->nullable();
            $table->string('provinsi')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('pumk_mitra', function (Blueprint $table) {
            $table->dropColumn(['kabupaten_kota', 'provinsi']);
        });
    }
};
