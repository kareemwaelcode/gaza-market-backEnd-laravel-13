<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class CmsLoginSecurityTest extends TestCase
{
    use RefreshDatabase;

    protected bool $actAsAdmin = false;

    private function makeAdmin(): Admin
    {
        $admin = new Admin();
        $admin->forceFill([
            'full_name' => 'Login Admin',
            'email' => 'login@test.local',
            'mobile' => '0590000001',
            'password' => Hash::make('correct-password'),
        ])->save();

        return $admin;
    }

    public function test_login_ignores_the_guard_sent_in_the_request(): void
    {
        $this->makeAdmin();

        $this->postJson('/cms/admins/login', [
            'email' => 'login@test.local',
            'password' => 'correct-password',
            'guard' => 'web',
        ])->assertOk()->assertJson(['icon' => 'success']);

        $this->assertAuthenticated('admins');
        $this->assertGuest('web');
    }

    public function test_login_route_only_accepts_the_admins_guard(): void
    {
        $this->postJson('/cms/web/login', ['email' => 'a@test.local', 'password' => 'x'])->assertNotFound();
        $this->postJson('/cms/nonsense/login', ['email' => 'a@test.local', 'password' => 'x'])->assertNotFound();
        $this->get('/cms/web/login')->assertNotFound();
    }

    public function test_login_regenerates_the_session_id(): void
    {
        $this->makeAdmin();

        $this->startSession();
        $before = session()->getId();

        $this->postJson('/cms/admins/login', [
            'email' => 'login@test.local',
            'password' => 'correct-password',
        ])->assertOk();

        $this->assertNotSame($before, session()->getId());
    }

    public function test_wrong_credentials_keep_the_same_response_shape(): void
    {
        $this->makeAdmin();

        $this->postJson('/cms/admins/login', ['email' => 'login@test.local', 'password' => 'wrong'])
            ->assertStatus(400)
            ->assertJson(['icon' => 'error', 'title' => 'Login is Failed']);
    }

    public function test_cms_login_is_throttled_after_repeated_failures(): void
    {
        $credentials = ['email' => 'nobody@test.local', 'password' => 'wrong-password'];

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/cms/admins/login', $credentials)->assertStatus(400);
        }

        $this->postJson('/cms/admins/login', $credentials)->assertStatus(429);
    }

    public function test_cms_logout_requires_post(): void
    {
        $this->signInAdmin();

        $this->get('/cms/admins/logout')->assertStatus(405);
    }

    public function test_cms_logout_signs_the_admin_out_and_redirects_to_login(): void
    {
        $this->makeAdmin();

        $this->postJson('/cms/admins/login', [
            'email' => 'login@test.local',
            'password' => 'correct-password',
        ])->assertOk();

        $this->post('/cms/admins/logout')
            ->assertRedirect(route('view.login', ['guard' => 'admins']));

        $this->assertGuest('admins');
    }
}