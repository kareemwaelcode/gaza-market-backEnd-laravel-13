<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\StoreCategory;
use App\Models\StoreCategoryGroup;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;

class StoreCategoryGroupController extends Controller
{
    public function index(): JsonResponse
    {
        $data = ApiCache::remember(
            [ApiCache::LOOKUPS],
            'store-category-groups',
            (int) config('prices.cache.lookups_ttl'),
            fn () => StoreCategoryGroup::query()
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
                ])->values()->all(),
        );

        return response()->json(['data' => $data]);
    }
}