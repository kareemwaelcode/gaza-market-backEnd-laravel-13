<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\Regions;
use App\Support\ApiCache;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    use GeneratesUniqueSlug;

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

    /** عرض سجل واحد (للأدمن): بديل صفحة edit بالـ CMS. */
    public function show(string $id): JsonResponse
    {
        $record = Regions::find($id);

        if ($record === null) {
            return response()->json(['icon' => 'error', 'title' => 'Region not found'], 404);
        }

        return response()->json(['data' => $this->payload($record)]);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = validator($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }

        $record = Regions::create([
            'region_name_en' => $request->input('region_name_en'),
            'region_name_ar' => $request->input('region_name_ar'),
            'region_sort_order' => $request->input('region_sort_order'),
            'region_slug' => $this->uniqueSlug(Regions::class, 'region_slug', $request->input('region_name_en')),
        ]);

        return response()->json(['data' => $this->payload($record)], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $record = Regions::find($id);

        if ($record === null) {
            return response()->json(['icon' => 'error', 'title' => 'Region not found'], 404);
        }

        $validator = validator($request->all(), $this->rules());

        if ($validator->fails()) {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }

        $record->region_name_en = $request->input('region_name_en');
        $record->region_name_ar = $request->input('region_name_ar');

        // الترتيب بينتغير فقط إذا انبعت بالطلب (الـ slug ما بيتغير بالتعديل، مثل الـ CMS).
        if ($request->has('region_sort_order')) {
            $record->region_sort_order = $request->input('region_sort_order');
        }

        $record->save();

        return response()->json(['data' => $this->payload($record)]);
    }

    public function destroy(string $id): JsonResponse
    {
        $record = Regions::find($id);

        if ($record === null) {
            return response()->json(['icon' => 'error', 'title' => 'Region not found'], 404);
        }

        try {
            $record->delete();
        } catch (QueryException $e) {
            return response()->json(['icon' => 'error', 'title' => 'Cannot delete a region that has areas'], 409);
        }

        return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully']);
    }

    /** @return array<string, mixed> */
    private function rules(): array
    {
        return [
            'region_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'region_name_ar' => ['required', 'string', 'max:45'],
            'region_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ];
    }

    /** @return array<string, mixed> */
    private function payload(Regions $record): array
    {
        return [
            'id' => $record->id,
            'slug' => $record->region_slug,
            'name_en' => $record->region_name_en,
            'name_ar' => $record->region_name_ar,
            'sort_order' => $record->region_sort_order,
        ];
    }
}