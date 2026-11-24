<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_items', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('source');
            $table->string('published_date', 100);
            $table->string('image_path');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        $newsFile = resource_path('data/news.json');
        if (! is_file($newsFile)) {
            throw new RuntimeException('Sumber berita awal tidak ditemukan: '.$newsFile);
        }

        $news = json_decode(file_get_contents($newsFile), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($news)) {
            throw new RuntimeException('Sumber berita awal harus berupa daftar berita.');
        }

        foreach ($news as $item) {
            DB::table('news_items')->insert([
                'title' => $item['title'],
                'source' => $item['source'],
                'published_date' => $item['date'],
                'image_path' => $item['image'],
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('news_items');
    }
};
