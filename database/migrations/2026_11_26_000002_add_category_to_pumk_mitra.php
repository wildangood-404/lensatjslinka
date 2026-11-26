<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pumk_mitra', function (Blueprint $table): void {
            $table->string('kategori_binaan', 16)->default('reguler')->index();
        });
    }

    public function down(): void
    {
        Schema::table('pumk_mitra', function (Blueprint $table): void {
            $table->dropColumn('kategori_binaan');
        });
    }
};
