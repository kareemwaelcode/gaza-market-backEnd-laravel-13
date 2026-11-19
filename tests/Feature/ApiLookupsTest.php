<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\NavigationItem;
use App\Models\Regions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ApiLookupsTest extends TestCase
{
    use RefreshDatabase;

    private function makeRegion(string $name): Regions
    {
        return Regions::create([
            'region_slug' => strtolower($name),
            'region_name_en' => $name,
            'region_name_ar' => $name,
            'region_sort_order' => 1,
        ]);
    }

    private function regionNames(): array
    {
        return collect($this->getJson('/api/regions')->assertOk()->json('data.data'))
            ->pluck('region_name_en')->all();
    }

    private function actAsApiAdmin(): void
    {
        $admin = Admin::forceCreate([
            'full_name' => 'Api Admin',
            'email' => 'api-admin@test.local',
            'mobile' => '0590000001',
            'password' => Hash::make('secret-pass-1'),
        ]);

        Passport::actingAs($admin, [], 'admins-api');
    }

    public function test_guest_cannot_write_regions(): void
    {
        $region = $this->makeRegion('Gaza');
        $payload = ['region_name_en' => 'Rafah', 'region_name_ar' => 'رفح'];

        $this->postJson('/api/regions', $payload)->assertUnauthorized();
        $this->putJson("/api/regions/{$region->id}", $payload)->assertUnauthorized();
        $this->deleteJson("/api/regions/{$region->id}")->assertUnauthorized();

        $this->assertDatabaseHas('regions', ['id' => $region->id, 'region_name_en' => 'Gaza']);
    }

    public function test_guest_can_still_read_regions(): void
    {
        $region = $this->makeRegion('Gaza');

        $this->getJson('/api/regions')->assertOk();
        $this->getJson("/api/regions/{$region->id}")->assertOk();
    }

    public function test_admin_can_write_regions(): void
    {
        $this->actAsApiAdmin();

        $this->postJson('/api/regions', [
            'region_name_en' => 'Rafah',
            'region_name_ar' => 'رفح',
        ])->assertOk();

        $this->assertDatabaseHas('regions', ['region_name_en' => 'Rafah']);
    }

    public function test_lookup_index_is_served_from_cache_on_the_second_request(): void
    {
        $this->makeRegion('Gaza');
        $this->regionNames();

        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->assertSame(['Gaza'], $this->regionNames());
        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_lookup_cache_is_invalidated_when_a_region_changes(): void
    {
        $this->makeRegion('Gaza');
        $this->assertSame(['Gaza'], $this->regionNames());

        $this->makeRegion('Rafah');

        $this->assertEqualsCanonicalizing(['Gaza', 'Rafah'], $this->regionNames());
    }

    public function test_navigation_items_cache_is_invalidated_when_an_item_changes(): void
    {
        $names = fn () => collect($this->getJson('/api/navigation-items')->assertOk()->json('data.data'))
            ->pluck('navigation_item_name_en')->all();

        $this->assertSame([], $names());

        NavigationItem::create([
            'navigation_item_slug' => 'home',
            'navigation_item_name_ar' => 'الرئيسية',
            'navigation_item_name_en' => 'Home',
            'navigation_item_icon' => 'home',
        ]);

        $this->assertSame(['Home'], $names());
    }

    public function test_public_lookups_are_rate_limited_per_ip(): void
    {
        for ($i = 0; $i < 120; $i++) {
            $this->getJson('/api/regions')->assertOk();
        }

        $this->getJson('/api/regions')->assertStatus(429);
    }
}