<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\Place;
use App\Models\StoreCategory;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Validation\Rule;

class PlaceController extends Controller
{
    private const PER_PAGE = 20;

    private const MAX_PER_PAGE = 50;

    public function index(Request $request): JsonResponse
    {
        $validator = validator($request->query(), [
            'region' => ['nullable', 'string', 'max:45'],
            'type' => ['nullable', Rule::in(Place::TYPES)],
            'category' => ['nullable', 'string', 'max:45'],
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
        $type = $request->query('type') ?: null;
        $category = $request->query('category') ?: null;
        $limit = (int) ($request->query('limit') ?: self::PER_PAGE);
        $pageNumber = Paginator::resolveCurrentPage();

        $build = fn () => $this->feedPayload($region, $type, $category, $limit);

        $payload = $pageNumber > (int) config('prices.cache.max_page')
            ? $build()
            : ApiCache::remember(
                [ApiCache::PLACES],
                'feed:'.md5(json_encode([$region, $type, $category, $limit, $pageNumber])),
                (int) config('prices.cache.places_ttl'),
                $build,
                fn (array $value) => $value['data'] === [],
            );

        return response()->json($payload);
    }

    public function show(string $slug): JsonResponse
    {
        $payload = ApiCache::remember(
            [ApiCache::PLACES],
            'detail:'.$slug,
            (int) config('prices.cache.place_detail_ttl'),
            fn () => $this->detailPayload($slug),
            fn ($value) => $value === false,
        );

        if ($payload === false) {
            return response()->json(['icon' => 'error', 'title' => 'Place not found'], 404);
        }

        return response()->json(['data' => $payload]);
    }

    private function feedPayload(?string $region, ?string $type, ?string $category, int $limit): array
    {
        $areaId = null;
        $categoryId = null;

        if ($region !== null) {
            $areaId = Area::where('area_slug', $region)->value('id');
        }

        if ($category !== null) {
            $categoryId = StoreCategory::where('store_category_slug', $category)->value('id');
        }

        if (($region !== null && $areaId === null) || ($category !== null && $categoryId === null)) {
            return [
                'data' => [],
                'meta' => [
                    'current_page' => Paginator::resolveCurrentPage(),
                    'per_page' => $limit,
                    'has_more' => false,
                ],
            ];
        }

        $page = Place::query()
            ->approved()
            ->when($areaId, fn ($q, $id) => $q->where('area_id', $id))
            ->when($type, fn ($q, $value) => $q->where('place_type', $value))
            ->when($categoryId, fn ($q, $id) => $q->where('store_category_id', $id))
            ->with([
                'area:id,area_slug,area_name_en,area_name_ar',
                'storeCategory:id,store_category_slug,store_category_name_en,store_category_name_ar',
            ])
            ->orderByDesc('place_approved_at')
            ->orderByDesc('id')
            ->simplePaginate($limit);

        $items = $page->getCollection()->map(fn (Place $place) => $this->card($place))->values();

        return [
            'data' => $items->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'per_page' => $page->perPage(),
                'has_more' => $page->hasMorePages(),
            ],
        ];
    }

    private function detailPayload(string $slug): array|false
    {
        $place = Place::query()
            ->approved()
            ->with([
                'area.region',
                'storeCategory:id,store_category_slug,store_category_name_en,store_category_name_ar',
            ])
            ->where('place_slug', $slug)
            ->first();

        if ($place === null) {
            return false;
        }

        return $this->card($place) + [
            'phone' => $place->place_phone,
            'whatsapp' => $place->place_whatsapp,
            'whatsapp_link' => $place->whatsapp_link,
            'region_name_ar' => $place->area?->region?->region_name_ar,
            'region_name_en' => $place->area?->region?->region_name_en,
            'approved_at' => $place->place_approved_at?->toJSON(),
        ];
    }

    private function card(Place $place): array
    {
        return [
            'id' => $place->id,
            'slug' => $place->place_slug,
            'name' => $place->place_name,
            'type' => $place->place_type,
            'address' => $place->place_address,
            'logo' => $place->logo_url,
            'is_open' => $place->place_is_open,
            'region' => $place->area?->area_slug,
            'area' => $place->area ? [
                'name_ar' => $place->area->area_name_ar,
                'name_en' => $place->area->area_name_en,
            ] : null,
            'sub_category' => $place->storeCategory ? [
                'slug' => $place->storeCategory->store_category_slug,
                'name_ar' => $place->storeCategory->store_category_name_ar,
                'name_en' => $place->storeCategory->store_category_name_en,
            ] : null,
        ];
    }
}