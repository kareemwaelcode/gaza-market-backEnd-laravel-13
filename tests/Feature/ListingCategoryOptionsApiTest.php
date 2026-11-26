<?php

namespace Tests\Feature;

use App\Models\ListingCategory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ListingCategoryOptionsApiTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/listing-categories/options';

    private function category(string $slug, bool $active = true, ?int $sort = 1): ListingCategory
    {
        return ListingCategory::create([
            'listing_category_slug' => $slug,
            'listing_category_name_en' => ucfirst($slug),
            'listing_category_name_ar' => 'تصنيف '.$slug,
            'listing_category_is_active' => $active,
            'listing_category_sort_order' => $sort,
        ]);
    }

    public function test_guest_can_read_active_categories_with_real_ids(): void
    {
        $category = $this->category('electronics');

        $response = $this->getJson(self::URL)->assertOk();

        $response->assertJsonPath('status', true);
        $response->assertJsonPath('data.categories.0.id', $category->id);
        $response->assertJsonPath('data.categories.0.slug', 'electronics');
        $response->assertJsonPath('data.categories.0.name_en', 'Electronics');
        $response->assertJsonPath('data.categories.0.name_ar', 'تصنيف electronics');
    }

    public function test_only_expected_fields_are_returned(): void
    {
        $this->category('electronics');

        $item = $this->getJson(self::URL)->assertOk()->json('data.categories.0');

        $this->assertSame(['id', 'name_ar', 'name_en', 'slug'], collect($item)->keys()->sort()->values()->all());
    }

    public function test_inactive_categories_are_hidden(): void
    {
        $this->category('electronics', true, 1);
        $this->category('hidden', false, 2);

        $slugs = array_column($this->getJson(self::URL)->assertOk()->json('data.categories'), 'slug');

        $this->assertSame(['electronics'], $slugs);
    }

    public function test_list_is_not_paginated(): void
    {
        foreach (range(1, 12) as $i) {
            $this->category('cat-'.$i, true, $i);
        }

        $this->assertCount(12, $this->getJson(self::URL)->assertOk()->json('data.categories'));
    }

    public function test_categories_follow_sort_order(): void
    {
        $this->category('second', true, 2);
        $this->category('first', true, 1);

        $slugs = array_column($this->getJson(self::URL)->assertOk()->json('data.categories'), 'slug');

        $this->assertSame(['first', 'second'], $slugs);
    }

    public function test_cache_is_invalidated_on_create_update_and_delete(): void
    {
        $first = $this->category('first', true, 1);

        $this->assertCount(1, $this->getJson(self::URL)->json('data.categories'));

        $second = $this->category('second', true, 2);
        $this->assertCount(2, $this->getJson(self::URL)->json('data.categories'));

        $second->update(['listing_category_is_active' => false]);
        $this->assertSame(['first'], array_column($this->getJson(self::URL)->json('data.categories'), 'slug'));

        $first->update(['listing_category_name_ar' => 'اسم جديد']);
        $this->assertSame('اسم جديد', $this->getJson(self::URL)->json('data.categories.0.name_ar'));

        $first->delete();
        $this->assertCount(0, $this->getJson(self::URL)->json('data.categories'));
    }

    public function test_options_route_is_not_captured_by_show(): void
    {
        $this->category('electronics');

        $this->getJson(self::URL)->assertOk()->assertJsonStructure(['data' => ['categories']]);
        $this->getJson('/api/listing-categories/999999')->assertNotFound();
    }

    public function test_existing_index_endpoint_is_unchanged(): void
    {
        $this->category('electronics');

        $this->getJson('/api/listing-categories')->assertOk()->assertJsonPath('data.data.0.listing_category_slug', 'electronics');
    }
}