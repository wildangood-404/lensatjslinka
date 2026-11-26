<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_items', function (Blueprint $table): void {
            $table->string('caption', 180)->nullable()->after('title');
            $table->longText('content')->nullable()->after('caption');
            $table->string('status', 20)->default('published')->after('published_date')->index();
            $table->string('image_path')->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('news_items')
            ->whereNull('image_path')
            ->update(['image_path' => 'images/news/tjsl-placeholder.svg']);

        Schema::table('news_items', function (Blueprint $table): void {
            $table->dropIndex(['status']);
            $table->dropColumn(['caption', 'content', 'status']);
            $table->string('image_path')->nullable(false)->change();
        });
    }
};
