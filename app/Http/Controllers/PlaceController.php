<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Place;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlaceController extends Controller
{
public function index(Request $request)
{
    $validator = validator($request->query(), [
        'status' => ['nullable', Rule::in(Place::STATUSES)],
        'type' => ['nullable', Rule::in(Place::TYPES)],
        'area' => ['nullable', 'integer'],
    ]);

    if ($validator->fails() && $request->expectsJson()) {
        throw new ValidationException($validator);
    }

    $ignoredFilters = $validator->errors()->keys();
    $filters = collect($request->query())
        ->only(['status', 'type', 'area'])
        ->except($ignoredFilters)
        ->all();

    $places = Place::query()
        ->with([
            'user:id,user_full_name,user_phone',
            'area:id,area_name_ar',
            'storeCategory:id,store_category_name_ar,store_category_name_en',
        ])
        ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('place_status', $status))
        ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('place_type', $type))
        ->when($filters['area'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
        ->orderByRaw("case when place_status = 'pending' then 0 else 1 end")
        ->orderByDesc('id')
        ->paginate(15)
        ->appends($filters);

    $areas = Area::ordered()->get(['id', 'area_name_ar']);

    return response()->view('cms.place.index', compact('places', 'areas', 'filters', 'ignoredFilters'));
}

public function show(string $id)
{
    $place = Place::with(['user', 'area.region', 'storeCategory'])->findOrFail($id);

    return response()->view('cms.place.show', compact('place'));
}

public function approve(string $id)
{
    $place = Place::findOrFail($id);

    $updated = Place::whereKey($place->id)
        ->whereIn('place_status', [Place::STATUS_PENDING, Place::STATUS_REJECTED])
        ->update([
            'place_status' => Place::STATUS_APPROVED,
            'place_approved_at' => now(),
            'updated_at' => now(),
        ]);

    if (! $updated) {
        return response()->json(['icon' => 'error', 'title' => 'Only pending or rejected places can be approved'], 409);
    }

    return response()->json(['redirect' => route('place.show', $place->id)], 200);
}

public function reject(string $id)
{
    $place = Place::findOrFail($id);

    $updated = Place::whereKey($place->id)
        ->whereIn('place_status', [Place::STATUS_PENDING, Place::STATUS_APPROVED])
        ->update([
            'place_status' => Place::STATUS_REJECTED,
            'place_approved_at' => null,
            'updated_at' => now(),
        ]);

    if (! $updated) {
        return response()->json(['icon' => 'error', 'title' => 'Only pending or approved places can be rejected'], 409);
    }

    return response()->json(['redirect' => route('place.show', $place->id)], 200);
}

public function destroy(string $id)
{
    $place = Place::findOrFail($id);

    $place->delete();

    Storage::disk('public')->deleteDirectory("places/{$place->id}");

    return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
}
}