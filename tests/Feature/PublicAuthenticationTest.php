<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PublicAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_redirect_guests_to_the_public_login(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Portal Informasi TJSL &amp; PUMK', false)
            ->assertSee('Login')
            ->assertSee('images/LOGO TJSL.png')
            ->assertSee('name="remember"', false)
            ->assertSee('id="home-showcase"', false);
        $this->get('/teras-tjsl')->assertRedirect(route('login'));
        $this->get('/profil')->assertRedirect(route('login'));

        $this->get(route('login'))
            ->assertRedirect(route('entry', ['login' => 1]));
        $this->get(route('entry', ['login' => 1]))
            ->assertOk()
            ->assertSee('x-data="{ loginOpen: true }"', false)
            ->assertSee('action="'.route('login.store').'"', false);
    }

    public function test_failed_login_returns_to_home_with_the_login_modal_open(): void
    {
        $this->followingRedirects()
            ->from(route('entry'))
            ->post(route('login.store'), [
                'username' => 'unknown-user',
                'password' => 'wrong-password',
            ])
            ->assertOk()
            ->assertSee('x-data="{ loginOpen: true }"', false)
            ->assertSee('Username atau password tidak sesuai.');
    }

    public function test_one_login_opens_admin_dashboard_and_monitoring_with_the_same_account(): void
    {
        $admin = User::factory()->create([
            'username' => 'TJSLINKA',
            'password' => Hash::make('TJSLHPVICTUS'),
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->post(route('login.store'), [
            'username' => 'TJSLINKA',
            'password' => 'TJSLHPVICTUS',
        ])->assertRedirect(route('admin.home'));
        $this->assertAuthenticatedAs($admin, 'web');
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->get(route('monitoring.tjsl'))
            ->assertOk()
            ->assertSeeInOrder([
                'id="monitoring-desktop-menu"',
                'Realisasi Anggaran Program TJSL',
                'Frequently Asked Questions (FAQ)',
            ], false)
            ->assertDontSee('videos/waterfall-bg.mp4');
        $this->get(route('admin.home'))->assertOk();
        $this->get(route('home'))->assertOk()->assertSee('id="home-showcase"', false);
    }

    public function test_user_role_login_opens_the_unified_home_page(): void
    {
        $user = User::factory()->create([
            'username' => 'portal-user',
            'password' => Hash::make('Password123'),
            'role' => 'user',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->post(route('login.store'), [
            'username' => 'portal-user',
            'password' => 'Password123',
        ])->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user, 'web');
        $this->get(route('home'))->assertOk()->assertSee('id="home-showcase"', false);
    }

    public function test_public_profile_renders_and_updates_the_shared_account(): void
    {
        $admin = User::factory()->create([
            'name' => 'User Admin',
            'nama_depan' => 'User',
            'nama_belakang' => 'Admin',
            'username' => 'public-admin',
            'password' => Hash::make('Password123'),
            'role' => 'admin',
            'is_active' => true,
            'must_change_password' => false,
        ]);

        $this->actingAs($admin, 'web')
            ->get(route('public.profile'))
            ->assertOk()
            ->assertSee('class="public-profile-card"', false)
            ->assertSee('User Admin')
            ->assertSee('Informasi Pribadi')
            ->assertSee('Username')
            ->assertSee('data-password-toggle', false)
            ->assertSee('Ubah Password');

        $this->put(route('public.profile.update'), [
            'nama_depan' => 'User',
            'nama_belakang' => 'Publik',
            'username' => 'public-admin-updated',
            'email' => 'public@example.com',
            'no_telephone' => '08123456789',
            'jabatan' => 'Karyawan',
            'alamat' => 'Madiun',
            'role' => 'super_admin',
        ])->assertRedirect();

        $this->assertDatabaseHas('users', [
            'id' => $admin->id,
            'name' => 'User Publik',
            'username' => 'public-admin-updated',
            'email' => 'public@example.com',
            'role' => 'admin',
        ]);
    }

    public function test_public_profile_rejects_a_username_owned_by_another_user(): void
    {
        $user = User::factory()->create(['username' => 'profile-owner']);
        User::factory()->create(['username' => 'already-taken']);

        $this->actingAs($user, 'web')
            ->put(route('public.profile.update'), [
                'nama_depan' => 'Profile',
                'nama_belakang' => 'Owner',
                'username' => 'already-taken',
                'email' => $user->email,
            ])
            ->assertSessionHasErrors('username');

        $this->assertSame('profile-owner', $user->fresh()->username);
    }
}
