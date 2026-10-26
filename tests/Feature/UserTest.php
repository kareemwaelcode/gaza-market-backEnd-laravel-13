<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\UserAccountService;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserTest extends TestCase
{
    use RefreshDatabase;

    private function user(string ...$states): User
    {
        $factory = UserFactory::new();

        foreach ($states as $state) {
            $factory = $factory->{$state}();
        }

        return $factory->create();
    }

    public function test_block_suspends_active_user_and_removes_sessions(): void
    {
        $user = $this->user();
        DB::table('sessions')->insert([
            'id' => 's1', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->postJson("/cms/admins/user_toggle_status/{$user->id}")
            ->assertOk()
            ->assertJson(['status' => 'suspended']);

        $this->assertSame('suspended', $user->fresh()->user_status);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_unblock_reactivates_suspended_user(): void
    {
        $user = $this->user('suspended');

        $this->postJson("/cms/admins/user_toggle_status/{$user->id}")
            ->assertOk()
            ->assertJson(['status' => 'active']);

        $this->assertSame('active', $user->fresh()->user_status);
    }

    public function test_toggle_on_deleted_status_returns_400(): void
    {
        $user = $this->user('deletedStatus');

        $this->postJson("/cms/admins/user_toggle_status/{$user->id}")->assertStatus(400);

        $this->assertSame('deleted', $user->fresh()->user_status);
    }

    public function test_toggle_unknown_user_returns_404(): void
    {
        $this->postJson('/cms/admins/user_toggle_status/999999')->assertNotFound();
    }

    public function test_delete_is_soft_and_marks_status_deleted(): void
    {
        $user = $this->user();
        DB::table('sessions')->insert([
            'id' => 's2', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time(),
        ]);

        $this->deleteJson("/cms/admins/user/{$user->id}")->assertOk();

        $this->assertNull(User::find($user->id));
        $row = User::withTrashed()->find($user->id);
        $this->assertNotNull($row->user_deleted_at);
        $this->assertSame('deleted', $row->user_status);
        $this->assertDatabaseMissing('sessions', ['user_id' => $user->id]);
    }

    public function test_deleted_user_phone_stays_reserved(): void
    {
        $user = $this->user();
        $phone = $user->user_phone;

        $this->deleteJson("/cms/admins/user/{$user->id}")->assertOk();

        $this->expectException(UniqueConstraintViolationException::class);

        UserFactory::new()->create(['user_phone' => $phone]);
    }

    public function test_phone_must_be_unique(): void
    {
        $user = $this->user();

        $this->expectException(UniqueConstraintViolationException::class);

        UserFactory::new()->create(['user_phone' => $user->user_phone]);
    }

    public function test_admin_role_is_not_allowed(): void
    {
        $this->expectException(QueryException::class);

        UserFactory::new()->create(['user_role' => 'admin']);
    }

    public function test_new_user_gets_database_defaults(): void
    {
        $user = (new UserAccountService())->create([
            'user_full_name' => 'Test User',
            'user_phone' => '0591234567',
            'user_role' => 'user',
        ]);

        $this->assertSame('active', $user->user_status);
        $this->assertSame('ar', $user->user_preferred_lang);
        $this->assertSame('light', $user->user_theme);
        $this->assertSame(0, $user->user_points_total);
        $this->assertNull($user->user_phone_verified_at);
    }

    public function test_service_create_marks_phone_verified_and_hashes_password(): void
    {
        $user = (new UserAccountService())->create([
            'user_full_name' => 'Owner',
            'user_phone' => '0597654321',
            'user_role' => 'store_owner',
            'user_password' => 'secret-pass-123',
            'phone_verified' => true,
        ]);

        $this->assertNotNull($user->user_phone_verified_at);
        $this->assertNotSame('secret-pass-123', $user->user_password);
        $this->assertTrue(Hash::check('secret-pass-123', $user->user_password));
    }

    public function test_system_fields_are_not_mass_assignable(): void
    {
        $user = new User([
            'user_full_name' => 'X',
            'user_phone' => '0590000000',
            'user_points_total' => 999,
            'user_phone_verified_at' => now(),
            'user_last_login_at' => now(),
            'user_avatar' => 'x.png',
        ]);

        $this->assertNull($user->user_points_total);
        $this->assertNull($user->user_phone_verified_at);
        $this->assertNull($user->user_last_login_at);
        $this->assertNull($user->user_avatar);
    }

    public function test_cms_index_lists_users_without_deleted_ones(): void
    {
        $visible = $this->user();
        $gone = $this->user();
        $this->deleteJson("/cms/admins/user/{$gone->id}")->assertOk();

        $this->get('/cms/admins/user')
            ->assertOk()
            ->assertSee($visible->user_phone)
            ->assertDontSee($gone->user_phone);
    }
}