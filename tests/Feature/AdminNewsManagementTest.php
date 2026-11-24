<?php

namespace Tests\Feature;

use App\Models\NewsItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminNewsManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
    }

    public function test_admin_can_manage_news_displayed_on_program_overview(): void
    {
        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);
        $initialNews = NewsItem::query()->firstOrFail();

        $this->actingAs($admin, 'admin')
            ->get(route('admin.news.index'))
            ->assertOk()
            ->assertSee('Berita TJSL INKA')
            ->assertSee($initialNews->title)
            ->assertSee('Tambah Berita');

        $this->post(route('admin.news.store'), [
            'title' => 'Berita Baru TJSL',
            'source' => 'INKA',
            'published_date' => '13 Oktober 2026',
            'image' => $this->pngUpload('berita-baru.png'),
        ])->assertRedirect(route('admin.news.index'));

        $newsItem = NewsItem::query()->where('title', 'Berita Baru TJSL')->firstOrFail();
        Storage::disk('public')->assertExists($newsItem->image_path);

        $this->actingAs($admin, 'web')
            ->get(route('program.overview'))
            ->assertOk()
            ->assertSee('Berita Baru TJSL')
            ->assertSee('INKA')
            ->assertSee('13 Oktober 2026')
            ->assertSee($newsItem->image_url, false);

        $this->actingAs($admin, 'admin')
            ->put(route('admin.news.update', $newsItem), [
                'title' => 'Berita Diperbarui TJSL',
                'source' => 'Media INKA',
                'published_date' => '14 Oktober 2026',
            ])
            ->assertRedirect(route('admin.news.index'));

        $newsItem->refresh();
        $this->assertSame('Berita Diperbarui TJSL', $newsItem->title);
        $this->assertSame('Media INKA', $newsItem->source);
        Storage::disk('public')->assertExists($newsItem->image_path);

        $this->delete(route('admin.news.destroy', $newsItem))
            ->assertRedirect();

        $this->assertDatabaseMissing('news_items', ['id' => $newsItem->id]);
        Storage::disk('public')->assertMissing($newsItem->image_path);
    }

    public function test_news_management_is_admin_only_and_new_items_require_images(): void
    {
        $superAdmin = User::factory()->create([
            'role' => 'super_admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($superAdmin, 'superadmin')
            ->get(route('admin.news.index'))
            ->assertForbidden();

        $admin = User::factory()->create([
            'role' => 'admin',
            'is_admin' => true,
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.news.store'), [
                'title' => 'Berita Tanpa Gambar',
                'source' => 'INKA',
                'published_date' => '13 Oktober 2026',
            ])
            ->assertSessionHasErrors('image');
    }

    private function pngUpload(string $name): UploadedFile
    {
        $contents = base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAADUlEQVR4nGP4z8AAAAMBAQDJ/pLvAAAAAElFTkSuQmCC',
            true,
        );

        return UploadedFile::fake()->createWithContent($name, $contents);
    }
}
