<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Listing;
use App\Models\ListingCategory;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\Rule;

class MarketController extends Controller
{
    private const PER_PAGE = 20;

    private const MAX_PER_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $validator = validator($request->query(), [
            'region' => ['nullable', 'string', 'max:45'],
            'category' => ['nullable', 'string', 'max:45'],
            'condition' => ['nullable', Rule::in(['new', 'used', 'urgent'])],
            'page' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.self::MAX_PER_PAGE],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $region = $request->query('region') ?: null;
        $category = $request->query('category') ?: null;
        $condition = $request->query('condition') ?: null;
        $limit = (int) ($request->query('limit') ?: self::PER_PAGE);
        $pageNumber = Paginator::resolveCurrentPage();

        $build = fn () => $this->feedPayload($region, $category, $condition, $limit);

        $payload = $pageNumber > (int) config('prices.cache.max_page')
            ? $build()
            : ApiCache::remember(
                [ApiCache::MARKET],
                'feed:'.md5(json_encode([$region, $category, $condition, $limit, $pageNumber])),
                (int) config('prices.cache.market_ttl'),
                $build,
                fn (array $value) => $value['data'] === [],
            );

        return response()->json($payload);
    }

    public function show(int $listing): JsonResponse
    {
        $payload = ApiCache::remember(
            [ApiCache::MARKET],
            'detail:'.$listing,
            (int) config('prices.cache.market_detail_ttl'),
            fn () => $this->detailPayload($listing),
            fn ($value) => $value === false,
        );

        if ($payload === false) {
            return response()->json(['icon' => 'error', 'title' => 'Listing not found'], 404);
        }

        return response()->json(['data' => $payload]);
    }

    private function feedPayload(?string $region, ?string $category, ?string $condition, int $limit): array
    {
        $areaId = null;
        $categoryId = null;

        if ($region !== null) {
            $areaId = Area::where('area_slug', $region)->value('id');
        }

        if ($category !== null) {
            $categoryId = ListingCategory::where('listing_category_slug', $category)->value('id');
        }

        if (($region !== null && $areaId === null) || ($category !== null && $categoryId === null)) {
            return $this->emptyPayload($limit);
        }

        $page = Listing::query()
            ->approved()
            ->when($areaId, fn ($q, $id) => $q->where('area_id', $id))
            ->when($categoryId, fn ($q, $id) => $q->where('listing_category_id', $id))
            ->when($condition, fn ($q, $value) => $q->where('listing_condition', $value))
            ->with([
                'area:id,area_slug,area_name_en,area_name_ar',
                'category:id,listing_category_slug,listing_category_name_en,listing_category_name_ar',
                'images' => fn ($q) => $q->where('listing_image_sort_order', 1),
            ])
            ->latestPublished()
            ->simplePaginate($limit);

        $items = $page->getCollection()->map(fn (Listing $listing) => [
            'id' => $listing->id,
            'title' => $listing->listing_title,
            'condition' => $listing->listing_condition,
            'price' => $listing->listing_price,
            'is_negotiable' => $listing->listing_is_negotiable,
            'region' => $listing->area?->area_slug,
            'area' => $listing->area ? [
                'name_ar' => $listing->area->area_name_ar,
                'name_en' => $listing->area->area_name_en,
            ] : null,
            'category' => $listing->category ? [
                'slug' => $listing->category->listing_category_slug,
                'name_ar' => $listing->category->listing_category_name_ar,
                'name_en' => $listing->category->listing_category_name_en,
            ] : null,
            'cover' => $listing->images->first()?->url,
            'published_at' => $listing->listing_published_at?->toJSON(),
        ])->values();

        return [
            'data' => $items->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'has_more' => $page->hasMorePages(),
            ],
        ];
    }

    private function emptyPayload(int $limit): array
    {
        return [
            'data' => [],
            'meta' => [
                'current_page' => Paginator::resolveCurrentPage(),
                'per_page' => $limit,
                'has_more' => false,
            ],
        ];
    }

    private function detailPayload(int $id): array|false
    {
        $listing = Listing::query()
            ->approved()
            ->with(['area.region', 'category', 'images', 'user'])
            ->find($id);

        if ($listing === null) {
            return false;
        }

        $seller = $listing->user;
        $sellerVisible = $seller !== null && ! $seller->trashed() && $seller->user_status !== 'deleted';

        return [
            'id' => $listing->id,
            'title' => $listing->listing_title,
            'condition' => $listing->listing_condition,
            'description' => $listing->listing_description,
            'price' => $listing->listing_price,
            'is_negotiable' => $listing->listing_is_negotiable,
            'phone' => $listing->listing_phone,
            'whatsapp' => $listing->listing_whatsapp,
            'whatsapp_link' => $listing->whatsapp_link,
            'region' => $listing->area?->area_slug,
            'area' => $listing->area ? [
                'name_ar' => $listing->area->area_name_ar,
                'name_en' => $listing->area->area_name_en,
                'region_name_ar' => $listing->area->region?->region_name_ar,
                'region_name_en' => $listing->area->region?->region_name_en,
            ] : null,
            'category' => $listing->category ? [
                'slug' => $listing->category->listing_category_slug,
                'name_ar' => $listing->category->listing_category_name_ar,
                'name_en' => $listing->category->listing_category_name_en,
            ] : null,
            'images' => $listing->images->pluck('url')->values()->all(),
            'seller_name' => $sellerVisible ? $seller->user_full_name : null,
            'published_at' => $listing->listing_published_at?->toJSON(),
        ];
    }
}