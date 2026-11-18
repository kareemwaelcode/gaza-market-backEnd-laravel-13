<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;

class CategoryGroupController extends Controller
{
    public function index(): JsonResponse
    {
    $data = ApiCache::remember(
        [ApiCache::LOOKUPS],
        'category-groups',
        (int) config('prices.cache.lookups_ttl'),
        fn () => CategoryGroup::query()
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
            ])->values()->all(),
        );

        return response()->json(['data' => $data]);
    }
}