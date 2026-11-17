<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListingCategory;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;

class ListingCategoryController extends Controller
{
    public function index(): JsonResponse
    {
        $data = ApiCache::remember(
            [ApiCache::LOOKUPS],
            'listing-categories',
            (int) config('prices.cache.lookups_ttl'),
            fn () => ListingCategory::query()
                ->active()
                ->ordered()
                ->get(['id', 'listing_category_slug', 'listing_category_name_en', 'listing_category_name_ar'])
                ->map(fn (ListingCategory $category) => [
                    'id' => $category->id,
                    'slug' => $category->listing_category_slug,
                    'name_en' => $category->listing_category_name_en,
                    'name_ar' => $category->listing_category_name_ar,
                ])->values()->all(),
        );

        return response()->json(['data' => $data]);
    }
}