<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;

class AreaController extends Controller
{
public function index(): JsonResponse
{
$data = ApiCache::remember(
    [ApiCache::LOOKUPS],
    'areas',
    (int) config('prices.cache.lookups_ttl'),
    fn () => Area::query()
        ->ordered()
        ->with('region:id,region_slug')
        ->get(['id', 'region_id', 'area_slug', 'area_name_en', 'area_name_ar'])
        ->map(fn (Area $area) => [
            'id' => $area->id,
            'slug' => $area->area_slug,
            'name_en' => $area->area_name_en,
            'name_ar' => $area->area_name_ar,
            'region_id' => $area->region_id,
            'region_slug' => $area->region?->region_slug,
        ])->values()->all(),
);

return response()->json(['data' => $data]);
}
}