<?php

namespace Tests\Feature;

use App\Models\Admin;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private function makeAdmin(string $email): Admin
    {
        $admin = new Admin();
        $admin->forceFill([
            'full_name' => 'Admin '.$email,
            'email' => $email,
            'mobile' => '0590000002',
            'password' => Hash::make('password-123'),
        ])->save();

        return $admin;
    }

    private function validData(array $override = []): array
    {
        $role = Role::firstOrCreate(['name' => 'editor', 'guard_name' => 'admins']);

        return array_merge([
            'full_name' => 'New Admin',
            'email' => 'new@test.local',
            'mobile' => '0591234567',
            'password' => 'password-123',
            'role_id' => $role->id,
        ], $override);
    }

    public function test_store_creates_an_admin_with_valid_data(): void
    {
        $this->postJson('/cms/admins/admin', $this->validData())
            ->assertOk()
            ->assertJson(['icon' => 'success']);

        $this->assertDatabaseHas('admins', ['email' => 'new@test.local']);
    }

    public function test_store_rejects_invalid_input_with_the_same_response_shape(): void
    {
        $cases = [
            ['email' => 'not-an-email'],
            ['password' => 'short'],
            ['password' => str_repeat('a', 256)],
            ['full_name' => str_repeat('a', 256)],
            ['mobile' => str_repeat('1', 21)],
        ];

        foreach ($cases as $override) {
            $this->postJson('/cms/admins/admin', $this->validData($override))
                ->assertStatus(400)
                ->assertJson(['icon' => 'error']);
        }

        $this->assertDatabaseCount('admins', 0);
    }

    public function test_store_rejects_a_duplicate_email(): void
    {
        $this->makeAdmin('taken@test.local');

        $this->postJson('/cms/admins/admin', $this->validData(['email' => 'taken@test.local']))
            ->assertStatus(400)
            ->assertJson(['icon' => 'error']);

        $this->assertDatabaseCount('admins', 1);
    }

    public function test_update_allows_keeping_the_same_email_but_rejects_another_admins_email(): void
    {
        $first = $this->makeAdmin('first@test.local');
        $this->makeAdmin('second@test.local');

        $this->postJson("/cms/admins/admins_update/{$first->id}", $this->validData(['email' => 'first@test.local']))
            ->assertOk();

        $this->postJson("/cms/admins/admins_update/{$first->id}", $this->validData(['email' => 'second@test.local']))
            ->assertStatus(400)
            ->assertJson(['icon' => 'error']);
    }

    public function test_admin_cannot_delete_themselves(): void
    {
        $self = $this->makeAdmin('self@test.local');
        $this->makeAdmin('other@test.local');

        $this->assertSame(1, $self->id);

        $this->deleteJson("/cms/admins/admin/{$self->id}")
            ->assertStatus(400)
            ->assertJson(['icon' => 'error']);

        $this->assertDatabaseHas('admins', ['id' => $self->id]);
    }

    public function test_admin_can_delete_another_admin(): void
    {
        $this->makeAdmin('self@test.local');
        $other = $this->makeAdmin('other@test.local');

        $this->deleteJson("/cms/admins/admin/{$other->id}")
            ->assertOk()
            ->assertJson(['icon' => 'success']);

        $this->assertDatabaseMissing('admins', ['id' => $other->id]);
    }

    public function test_the_last_admin_cannot_be_deleted(): void
    {
        $only = $this->makeAdmin('only@test.local');
        $this->assertSame(1, $only->id);

        $this->actAsAdmin = false;
        $this->signInAdminAs(99);

        $this->deleteJson("/cms/admins/admin/{$only->id}")
            ->assertStatus(400)
            ->assertJson(['icon' => 'error']);

        $this->assertDatabaseHas('admins', ['id' => $only->id]);
    }

    private function signInAdminAs(int $id): void
    {
        $admin = (new Admin)->forceFill([
            'id' => $id,
            'full_name' => 'Other Session',
            'email' => 'session@test.local',
            'mobile' => '0590000009',
        ]);

        $this->app['auth']->guard('admins')->setUser($admin);
    }
}