<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('no_surat')->nullable();
            $table->date('tanggal_surat')->nullable();
            $table->date('tanggal_program')->nullable();
            $table->json('jenis_bantuan')->nullable();
            $table->unsignedBigInteger('nominal_uang_tunai')->nullable();
            $table->text('detail_jasa')->nullable();
            $table->string('pic_nama')->nullable();
            $table->string('pic_telepon')->nullable();
            $table->decimal('rencana_anggaran', 18, 2)->nullable()->change();
        });

        DB::table('programs')->whereNull('jenis_bantuan')->update([
            'jenis_bantuan' => json_encode(['uang_tunai']),
            'nominal_uang_tunai' => DB::raw('rencana_anggaran'),
        ]);

        Schema::create('program_assistance_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->enum('tipe', ['barang', 'jasa']);
            $table->string('nama_item');
            $table->string('jumlah')->nullable();
            $table->string('keterangan')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('program_assistance_items');

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'no_surat',
                'tanggal_surat',
                'tanggal_program',
                'jenis_bantuan',
                'nominal_uang_tunai',
                'detail_jasa',
                'pic_nama',
                'pic_telepon',
            ]);
            $table->decimal('rencana_anggaran', 18, 2)->default(0)->change();
        });
    }
};
