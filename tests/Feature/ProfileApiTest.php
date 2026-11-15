<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class ProfileApiTest extends TestCase
{
    use BuildsProductPrices;
    use RefreshDatabase;

    public function test_guests_cannot_update_or_upload(): void
    {
        $this->patchJson('/api/auth/me', ['full_name' => 'X Y'])->assertStatus(401);
        $this->postJson('/api/auth/me/avatar')->assertStatus(401);
    }

    public function test_it_updates_the_allowed_fields_and_returns_the_auth_shape(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();

        $this->actingAs($user)->patchJson('/api/auth/me', [
            'full_name' => 'أحمد علي',
            'preferred_lang' => 'en',
            'theme' => 'dark',
            'area_id' => $lookups['area']->id,
        ])->assertOk()
            ->assertJsonPath('data.full_name', 'أحمد علي')
            ->assertJsonPath('data.preferred_lang', 'en')
            ->assertJsonPath('data.theme', 'dark')
            ->assertJsonPath('data.area_id', $lookups['area']->id);

        $user->refresh();
        $this->assertSame('أحمد علي', $user->user_full_name);
        $this->assertSame('dark', $user->user_theme);
    }

    public function test_partial_update_leaves_other_fields_alone_and_area_can_be_cleared(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser('اسم قديم');
        $user->forceFill(['area_id' => $lookups['area']->id])->save();

        $this->actingAs($user)->patchJson('/api/auth/me', ['theme' => 'dark'])->assertOk();
        $this->assertSame('اسم قديم', $user->refresh()->user_full_name);
        $this->assertSame($lookups['area']->id, $user->area_id);

        $this->actingAs($user)->patchJson('/api/auth/me', ['area_id' => null])->assertOk();
        $this->assertNull($user->refresh()->area_id);
    }

    public function test_protected_fields_are_ignored(): void
    {
        $user = $this->makeUser();
        $phone = $user->user_phone;

        $this->actingAs($user)->patchJson('/api/auth/me', [
            'full_name' => 'اسم جديد',
            'user_role' => 'store_owner',
            'role' => 'store_owner',
            'user_status' => 'suspended',
            'user_phone' => '0599999999',
            'phone' => '0599999999',
            'user_points_total' => 9999,
            'points_total' => 9999,
            'id' => 12345,
        ])->assertOk();

        $user->refresh();
        $this->assertSame('user', $user->user_role);
        $this->assertSame('active', $user->user_status);
        $this->assertSame($phone, $user->user_phone);
        $this->assertSame(0, $user->user_points_total);
    }

    public function test_validation_errors(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->patchJson('/api/auth/me', [])->assertStatus(422);
        $this->actingAs($user)->patchJson('/api/auth/me', ['full_name' => ''])->assertStatus(422);
        $this->actingAs($user)->patchJson('/api/auth/me', ['full_name' => str_repeat('ا', 46)])->assertStatus(422);
        $this->actingAs($user)->patchJson('/api/auth/me', ['preferred_lang' => 'fr'])->assertStatus(422);
        $this->actingAs($user)->patchJson('/api/auth/me', ['theme' => 'blue'])->assertStatus(422);
        $this->actingAs($user)->patchJson('/api/auth/me', ['area_id' => 999999])->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION');
    }

    public function test_avatar_is_stored_on_the_public_disk_and_the_old_one_is_deleted(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $first = $this->actingAs($user)->post('/api/auth/me/avatar', [
            'avatar' => UploadedFile::fake()->image('a.jpg'),
        ], ['Accept' => 'application/json'])->assertOk();

        $oldPath = $user->refresh()->user_avatar;
        $this->assertStringStartsWith("avatars/{$user->id}/", $oldPath);
        Storage::disk('public')->assertExists($oldPath);
        $this->assertNotNull($first->json('data.avatar_url'));

        $this->actingAs($user)->post('/api/auth/me/avatar', [
            'avatar' => UploadedFile::fake()->image('b.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $newPath = $user->refresh()->user_avatar;
        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_avatar_rejects_non_images_and_oversized_files(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $this->actingAs($user)->post('/api/auth/me/avatar', [
            'avatar' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->actingAs($user)->post('/api/auth/me/avatar', [
            'avatar' => UploadedFile::fake()->image('big.jpg')->size(3000),
        ], ['Accept' => 'application/json'])->assertStatus(422);

        $this->actingAs($user)->post('/api/auth/me/avatar', [], ['Accept' => 'application/json'])->assertStatus(422);

        $this->assertNull($user->refresh()->user_avatar);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_external_https_avatar_is_not_touched_when_replaced(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $user->forceFill(['user_avatar' => 'https://cdn.example.com/a.jpg'])->save();

        $this->actingAs($user)->post('/api/auth/me/avatar', [
            'avatar' => UploadedFile::fake()->image('b.png'),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertStringStartsWith('avatars/', $user->refresh()->user_avatar);
    }

    public function test_suspended_user_cannot_update(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['user_status' => 'suspended'])->save();

        $this->actingAs($user)->patchJson('/api/auth/me', ['theme' => 'dark'])->assertStatus(401);
    }
}