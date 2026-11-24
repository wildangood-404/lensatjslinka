<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('programs', function (Blueprint $table) {
            $table->string('tipe_kerjasama', 16)->nullable()->after('jenis_kerjasama');
            $table->string('jenis_pengajuan', 32)->nullable()->after('tipe_kerjasama');
            $table->string('nama_mitra')->nullable()->after('jenis_pengajuan');
            $table->string('kategori_program')->nullable()->after('pillar_id');
            $table->text('catatan_terakhir')->nullable()->after('rejected_reason');
        });

        Schema::table('program_documents', function (Blueprint $table) {
            $table->string('kategori_tahap', 32)->default('proposal')->after('document_type');
            $table->string('nama_file_asli')->nullable()->after('nama_dokumen');
        });

        Schema::create('program_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('program_id')->constrained()->cascadeOnDelete();
            $table->string('from_status')->nullable();
            $table->string('to_status');
            $table->foreignId('changed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('catatan')->nullable();
            $table->timestamps();
            $table->index(['program_id', 'created_at']);
        });

        DB::table('programs')->whereNull('tipe_kerjasama')->update([
            'tipe_kerjasama' => DB::raw("CASE WHEN jenis_kerjasama = 'non_pks' THEN 'NON PKS' ELSE 'PKS' END"),
        ]);
        DB::table('programs')->whereNull('jenis_pengajuan')->update(['jenis_pengajuan' => 'proposal_tjsl']);
        DB::table('programs')->whereNull('nama_mitra')->update(['nama_mitra' => DB::raw('mitra_program')]);
        foreach (DB::table('programs')->select('id', 'pillar_id')->get() as $program) {
            $category = DB::table('pillars')->where('id', $program->pillar_id)->value('name');
            DB::table('programs')->where('id', $program->id)->update(['kategori_program' => $category]);
        }
        DB::table('program_documents')->whereNull('nama_file_asli')->update([
            'nama_file_asli' => DB::raw('nama_dokumen'),
        ]);
        DB::table('program_documents')->where('document_type', 'kelengkapan_survei')
            ->update(['kategori_tahap' => 'survei']);
        DB::table('program_documents')->where('document_type', 'bast')
            ->update(['kategori_tahap' => 'laporan_akhir']);

        if (Schema::hasTable('status_logs')) {
            foreach (DB::table('status_logs')->where('related_type', 'program')->orderBy('id')->get() as $log) {
                DB::table('program_logs')->insert([
                    'program_id' => $log->related_id,
                    'from_status' => $log->from_status,
                    'to_status' => $log->to_status,
                    'changed_by' => $log->changed_by,
                    'catatan' => $log->note,
                    'created_at' => $log->created_at,
                    'updated_at' => $log->created_at,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('program_logs');

        Schema::table('program_documents', function (Blueprint $table) {
            $table->dropColumn(['kategori_tahap', 'nama_file_asli']);
        });

        Schema::table('programs', function (Blueprint $table) {
            $table->dropColumn([
                'tipe_kerjasama',
                'jenis_pengajuan',
                'nama_mitra',
                'kategori_program',
                'catatan_terakhir',
            ]);
        });
    }
};
