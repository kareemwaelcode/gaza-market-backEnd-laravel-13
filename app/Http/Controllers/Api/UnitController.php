<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;

class UnitController extends Controller
{
    public function index(): JsonResponse
    {
        $data = ApiCache::remember(
            [ApiCache::LOOKUPS],
            'units',
            (int) config('prices.cache.lookups_ttl'),
            fn () => Unit::query()
                ->where('unit_is_active', true)
                ->ordered()
                ->get(['id', 'unit_slug', 'unit_name_en', 'unit_name_ar'])
                ->map(fn (Unit $unit) => [
                    'id' => $unit->id,
                    'slug' => $unit->unit_slug,
                    'name_en' => $unit->unit_name_en,
                    'name_ar' => $unit->unit_name_ar,
                ])->values()->all(),
        );

        return response()->json(['data' => $data]);
    }
}