<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Regions;
use App\Models\Unit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductOptionsApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroup(): CategoryGroup
    {
        return CategoryGroup::create([
            'category_group_slug' => 'food',
            'category_group_name_en' => 'Food',
            'category_group_name_ar' => 'مواد غذائية',
            'category_group_sort_order' => 1,
        ]);
    }

    private function makeCategory(CategoryGroup $group, string $slug, bool $active = true, int $sort = 1): Category
    {
        return Category::create([
            'category_slug' => $slug,
            'category_name_en' => ucfirst($slug),
            'category_name_ar' => 'فئة '.$slug,
            'category_is_active' => $active,
            'category_sort_order' => $sort,
            'category_group_id' => $group->id,
        ]);
    }

    private function makeUnit(string $slug, bool $active = true, int $sort = 1): Unit
    {
        return Unit::create([
            'unit_slug' => $slug,
            'unit_name_en' => ucfirst($slug),
            'unit_name_ar' => 'وحدة '.$slug,
            'unit_is_active' => $active,
            'unit_sort_order' => $sort,
        ]);
    }

    private function makeArea(string $slug = 'al-rimal'): Area
    {
        $region = Regions::create([
            'region_slug' => 'gaza-'.$slug,
            'region_name_en' => 'Gaza',
            'region_name_ar' => 'غزة',
            'region_sort_order' => 1,
        ]);

        return Area::create([
            'area_slug' => $slug,
            'area_name_en' => 'Al-Rimal',
            'area_name_ar' => 'الرمال',
            'area_sort_order' => 1,
            'region_id' => $region->id,
        ]);
    }

    public function test_guest_can_read_options_with_real_ids(): void
    {
        $group = $this->makeGroup();
        $category = $this->makeCategory($group, 'oils');
        $unit = $this->makeUnit('kilogram-kg');
        $area = $this->makeArea();

        $response = $this->getJson('/api/product-options')->assertOk();

        $response->assertJsonPath('data.categories.0.id', $category->id);
        $response->assertJsonPath('data.categories.0.name_ar', 'فئة oils');
        $response->assertJsonPath('data.units.0.id', $unit->id);
        $response->assertJsonPath('data.areas.0.id', $area->id);
        $response->assertJsonPath('data.areas.0.name_en', 'Al-Rimal');
    }

    public function test_inactive_categories_and_units_are_hidden(): void
    {
        $group = $this->makeGroup();
        $this->makeCategory($group, 'oils', true, 1);
        $this->makeCategory($group, 'hidden-cat', false, 2);
        $this->makeUnit('kg', true, 1);
        $this->makeUnit('hidden-unit', false, 2);
        $this->makeArea();

        $data = $this->getJson('/api/product-options')->assertOk()->json('data');

        $this->assertSame(['oils'], array_column($data['categories'], 'slug'));
        $this->assertSame(['kg'], array_column($data['units'], 'slug'));
    }

    public function test_lists_are_not_paginated(): void
    {
        $group = $this->makeGroup();

        foreach (range(1, 12) as $i) {
            $this->makeCategory($group, 'cat-'.$i, true, $i);
        }

        $this->makeUnit('kg');
        $this->makeArea();

        $categories = $this->getJson('/api/product-options')->assertOk()->json('data.categories');

        $this->assertCount(12, $categories);
    }

    public function test_options_follow_sort_order(): void
    {
        $group = $this->makeGroup();
        $this->makeCategory($group, 'second', true, 2);
        $this->makeCategory($group, 'first', true, 1);
        $this->makeUnit('kg');
        $this->makeArea();

        $slugs = array_column(
            $this->getJson('/api/product-options')->assertOk()->json('data.categories'),
            'slug'
        );

        $this->assertSame(['first', 'second'], $slugs);
    }

    public function test_new_unit_appears_after_cache_invalidation(): void
    {
        $group = $this->makeGroup();
        $this->makeCategory($group, 'oils');
        $this->makeUnit('kg');
        $this->makeArea();

        $before = $this->getJson('/api/product-options')->assertOk()->json('data.units');
        $this->assertCount(1, $before);

        $this->makeUnit('liter', true, 2);

        $after = $this->getJson('/api/product-options')->assertOk()->json('data.units');
        $this->assertCount(2, $after);
    }

    public function test_endpoint_is_read_only(): void
    {
        $this->postJson('/api/product-options', [])->assertStatus(405);
        $this->deleteJson('/api/product-options')->assertStatus(405);
    }
}