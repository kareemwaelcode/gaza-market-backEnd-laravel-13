<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_delete_a_role_of_every_allowed_guard(): void
    {
        foreach (['admins', 'web'] as $guard) {
            $role = Role::create(['name' => "role-{$guard}", 'guard_name' => $guard]);

            $this->deleteJson("/cms/admins/roles/{$role->id}")
                ->assertOk()
                ->assertJson(['icon' => 'success']);

            $this->assertDatabaseMissing('roles', ['id' => $role->id]);
        }
    }

    public function test_store_creates_a_role_with_an_allowed_guard(): void
    {
        $this->postJson('/cms/admins/roles', ['guard_name' => 'admins', 'name' => 'مشرف'])
            ->assertOk()
            ->assertJson(['icon' => 'success']);

        $this->assertDatabaseHas('roles', ['name' => 'مشرف', 'guard_name' => 'admins']);
    }

    public function test_store_rejects_a_guard_that_is_not_in_auth_config(): void
    {
        foreach (['admin', 'user', 'nonsense'] as $guard) {
            $this->postJson('/cms/admins/roles', ['guard_name' => $guard, 'name' => 'x'])
                ->assertStatus(400)
                ->assertJson(['icon' => 'error']);
        }

        $this->assertDatabaseCount('roles', 0);
    }

    public function test_update_changes_a_role_and_rejects_a_bad_guard(): void
    {
        $role = Role::create(['name' => 'old', 'guard_name' => 'admins']);

        $this->postJson("/cms/admins/roles_update/{$role->id}", ['guard_name' => 'web', 'name' => 'new'])
            ->assertOk()
            ->assertJson(['icon' => 'success']);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'name' => 'new', 'guard_name' => 'web']);

        $this->postJson("/cms/admins/roles_update/{$role->id}", ['guard_name' => 'admin', 'name' => 'bad'])
            ->assertStatus(400);

        $this->assertDatabaseHas('roles', ['id' => $role->id, 'guard_name' => 'web']);
    }
}