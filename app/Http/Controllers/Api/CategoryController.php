<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;

class CategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $data = ApiCache::remember(
            [ApiCache::LOOKUPS],
            'categories',
            (int) config('prices.cache.lookups_ttl'),
            fn () => Category::query()
                ->where('category_is_active', true)
                ->ordered()
                ->with('categoryGroup:id,category_group_slug')
                ->get(['id', 'category_group_id', 'category_slug', 'category_name_en', 'category_name_ar'])
                ->map(fn (Category $category) => [
                    'id' => $category->id,
                    'slug' => $category->category_slug,
                    'name_en' => $category->category_name_en,
                    'name_ar' => $category->category_name_ar,
                    'group_id' => $category->category_group_id,
                    'group_slug' => $category->categoryGroup?->category_group_slug,
                ])->values()->all(),
        );

        return response()->json(['data' => $data]);
    }
}