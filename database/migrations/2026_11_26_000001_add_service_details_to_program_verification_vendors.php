<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('program_verification_vendors', function (Blueprint $table) {
            $table->string('tipe')->default('barang')->after('program_id');
            $table->string('rincian_jasa')->nullable()->after('rincian_barang');
        });
    }

    public function down(): void
    {
        Schema::table('program_verification_vendors', function (Blueprint $table) {
            $table->dropColumn(['tipe', 'rincian_jasa']);
        });
    }
};
