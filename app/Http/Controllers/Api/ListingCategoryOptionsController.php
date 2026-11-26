<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ListingCategory;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;

/**
 * GET /api/listing-categories/options  (عام - قراءة فقط)
 *
 * تصنيفات إعلانات البيع والشراء من جدول listing_categories:
 * الفعّالة فقط، مرتبة، بدون pagination، مع الـ id الحقيقي الذي
 * يتحقق منه StoreListingRequest (listing_category_id).
 *
 * الكاش يُفرَّغ تلقائياً عند أي تعديل على ListingCategory (CacheInvalidation).
 */
class ListingCategoryOptionsController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $data = ApiCache::lookup('listing-category-options', fn () => [
            'categories' => ListingCategory::query()
                ->active()
                ->ordered()
                ->get(['id', 'listing_category_slug', 'listing_category_name_en', 'listing_category_name_ar'])
                ->map(fn (ListingCategory $c) => [
                    'id' => $c->id,
                    'slug' => $c->listing_category_slug,
                    'name_en' => $c->listing_category_name_en,
                    'name_ar' => $c->listing_category_name_ar,
                ])->all(),
        ], paged: false);

        return response()->json([
            'status' => true,
            'message' => 'Listing category options',
            'data' => $data,
        ], 200);
    }
}