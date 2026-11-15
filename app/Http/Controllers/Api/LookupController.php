<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\ListingCategory;
use App\Models\Place;
use App\Models\Regions;
use App\Models\StoreCategory;
use App\Models\StoreCategoryGroup;
use App\Models\Unit;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;

class LookupController extends Controller
{
    public function index(): JsonResponse
    {
        $payload = ApiCache::remember(
            [ApiCache::LOOKUPS],
            'all',
            (int) config('prices.cache.lookups_ttl'),
            fn () => $this->payload(),
        );

        return response()->json(['data' => $payload]);
    }

    private function payload(): array
    {
        $regions = Regions::query()
            ->ordered()
            ->with('areas:id,region_id,area_slug,area_name_en,area_name_ar')
            ->get(['id', 'region_slug', 'region_name_en', 'region_name_ar'])
            ->map(fn (Regions $region) => [
                'id' => $region->id,
                'slug' => $region->region_slug,
                'name_en' => $region->region_name_en,
                'name_ar' => $region->region_name_ar,
                'areas' => $region->areas->map(fn ($area) => [
                    'id' => $area->id,
                    'slug' => $area->area_slug,
                    'name_en' => $area->area_name_en,
                    'name_ar' => $area->area_name_ar,
                ])->values()->all(),
            ])->values()->all();

        $units = Unit::query()
            ->where('unit_is_active', true)
            ->ordered()
            ->get(['id', 'unit_slug', 'unit_name_en', 'unit_name_ar'])
            ->map(fn (Unit $unit) => [
                'id' => $unit->id,
                'slug' => $unit->unit_slug,
                'name_en' => $unit->unit_name_en,
                'name_ar' => $unit->unit_name_ar,
            ])->values()->all();

        $categoryGroups = CategoryGroup::query()
            ->ordered()
            ->with(['categories' => fn ($query) => $query
                ->where('category_is_active', true)
                ->select(['id', 'category_group_id', 'category_slug', 'category_name_en', 'category_name_ar', 'category_sort_order'])
                ->orderBy('category_sort_order')
                ->orderBy('id')])
            ->get(['id', 'category_group_slug', 'category_group_name_en', 'category_group_name_ar'])
            ->map(fn (CategoryGroup $group) => [
                'id' => $group->id,
                'slug' => $group->category_group_slug,
                'name_en' => $group->category_group_name_en,
                'name_ar' => $group->category_group_name_ar,
                'categories' => $group->categories->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->category_slug,
                    'name_en' => $category->category_name_en,
                    'name_ar' => $category->category_name_ar,
                ])->values()->all(),
            ])->values()->all();

        $storeCategoryGroups = StoreCategoryGroup::query()
            ->ordered()
            ->with(['storeCategories' => fn ($query) => $query
                ->where('store_category_is_active', true)
                ->select(['id', 'store_category_group_id', 'store_category_slug', 'store_category_variant_key', 'store_category_name_en', 'store_category_name_ar', 'store_category_sort_order'])
                ->orderBy('store_category_sort_order')
                ->orderBy('id')])
            ->get(['id', 'store_category_group_slug', 'store_category_group_name_en', 'store_category_group_name_ar'])
            ->map(fn (StoreCategoryGroup $group) => [
                'id' => $group->id,
                'slug' => $group->store_category_group_slug,
                'name_en' => $group->store_category_group_name_en,
                'name_ar' => $group->store_category_group_name_ar,
                'categories' => $group->storeCategories->map(fn (StoreCategory $category) => [
                    'id' => $category->id,
                    'slug' => $category->store_category_slug,
                    'variant_key' => $category->store_category_variant_key,
                    'name_en' => $category->store_category_name_en,
                    'name_ar' => $category->store_category_name_ar,
                ])->values()->all(),
            ])->values()->all();

        $listingCategories = ListingCategory::query()
            ->active()
            ->ordered()
            ->get(['id', 'listing_category_slug', 'listing_category_name_en', 'listing_category_name_ar'])
            ->map(fn (ListingCategory $category) => [
                'id' => $category->id,
                'slug' => $category->listing_category_slug,
                'name_en' => $category->listing_category_name_en,
                'name_ar' => $category->listing_category_name_ar,
            ])->values()->all();

        return [
            'regions' => $regions,
            'units' => $units,
            'category_groups' => $categoryGroups,
            'store_category_groups' => $storeCategoryGroups,
            'listing_categories' => $listingCategories,
            'listing_conditions' => ['new', 'used', 'urgent'],
            'place_types' => Place::TYPES,
        ];
    }
}