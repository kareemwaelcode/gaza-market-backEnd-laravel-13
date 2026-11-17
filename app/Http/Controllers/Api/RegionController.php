<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Regions;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;

class RegionController extends Controller
{
public function index(): JsonResponse
{
    $data = ApiCache::remember(
    [ApiCache::LOOKUPS],
    'regions',
    (int) config('prices.cache.lookups_ttl'),
    fn () => Regions::query()
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
    ])->values()->all(),
);

return response()->json(['data' => $data]);
}
}