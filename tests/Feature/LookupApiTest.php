<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\ListingCategory;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketData;
use Tests\TestCase;

class LookupApiTest extends TestCase
{
    use BuildsMarketData;
    use RefreshDatabase;

    public function test_lookups_return_regions_with_areas_and_only_active_items(): void
    {
        $area = $this->makeArea();
        $active = $this->makeListingCategory(true);
        $this->makeListingCategory(false);

        $unit = new Unit();
        $unit->unit_slug = 'kg';
        $unit->unit_name_en = 'Kg';
        $unit->unit_name_ar = 'كغ';
        $unit->unit_is_active = true;
        $unit->save();

        $hidden = new Unit();
        $hidden->unit_slug = 'hidden';
        $hidden->unit_name_en = 'Hidden';
        $hidden->unit_name_ar = 'مخفي';
        $hidden->unit_is_active = false;
        $hidden->save();

        $response = $this->getJson('/api/lookups');

        $response->assertOk()
            ->assertJsonPath('data.regions.0.areas.0.slug', $area->area_slug)
            ->assertJsonCount(1, 'data.listing_categories')
            ->assertJsonPath('data.listing_categories.0.slug', $active->listing_category_slug)
            ->assertJsonCount(1, 'data.units')
            ->assertJsonPath('data.units.0.slug', 'kg')
            ->assertJsonPath('data.listing_conditions', ['new', 'used', 'urgent']);
    }

    public function test_lookups_do_not_set_cookies(): void
    {
        $response = $this->getJson('/api/lookups');

        $response->assertOk();
        $this->assertSame([], $response->headers->getCookies());
    }

    public function test_lookups_cache_is_invalidated_when_a_lookup_changes(): void
    {
        $this->getJson('/api/lookups')->assertJsonCount(0, 'data.listing_categories');

        $this->makeListingCategory(true);

        $this->getJson('/api/lookups')->assertJsonCount(1, 'data.listing_categories');

        $area = $this->makeArea();
        $area->area_name_ar = 'اسم جديد';
        $area->save();

        $this->getJson('/api/lookups')->assertJsonPath('data.regions.0.areas.0.name_ar', 'اسم جديد');

        Area::destroy($area->id);

        $this->getJson('/api/lookups')->assertJsonCount(0, 'data.regions.0.areas');
    }

    public function test_inactive_listing_category_is_hidden_after_toggle(): void
    {
        $category = $this->makeListingCategory(true);

        $this->getJson('/api/lookups')->assertJsonCount(1, 'data.listing_categories');

        $category->listing_category_is_active = false;
        $category->save();

        $this->getJson('/api/lookups')->assertJsonCount(0, 'data.listing_categories');
        $this->assertSame(1, ListingCategory::count());
    }
}