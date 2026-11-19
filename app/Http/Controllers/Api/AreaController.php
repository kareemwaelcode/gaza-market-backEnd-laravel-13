<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Support\ApiCache;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class AreaController extends Controller implements HasMiddleware
{
    use GeneratesUniqueSlug;

    public static function middleware(): array
    {
        return [
            new Middleware('auth:admins-api', except: ['index', 'show']),
        ];
    }

    public function index(): JsonResponse
    {
        $areas = ApiCache::lookup('areas', fn () => Area::with('region:id,region_name_en')->ordered()->paginate(10)->toArray());

        return response()->json([
            'status' => true,
            'message' => 'Data of Areas',
            'data' => $areas,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->areaValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        Area::create([
            'area_slug' => $this->uniqueSlug(Area::class, 'area_slug', $request->input('area_name_en')),
            'area_name_en' => $request->input('area_name_en'),
            'area_name_ar' => $request->input('area_name_ar'),
            'region_id' => $request->input('region_id'),
            'area_sort_order' => $request->input('area_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Created Successfully',
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $area = Area::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Area',
            'data' => $area,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $area = Area::findOrFail($id);

        $validator = $this->areaValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $area->update([
            'area_name_en' => $request->input('area_name_en'),
            'area_name_ar' => $request->input('area_name_ar'),
            'region_id' => $request->input('region_id'),
            'area_sort_order' => $request->input('area_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $area = Area::findOrFail($id);

        try {
            $area->delete();
        } catch (QueryException $e) {
            return response()->json([
                'icon' => 'error',
                'title' => 'Cannot delete an area that has users',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function areaValidator(Request $request): ValidatorContract
    {
        return validator($request->all(), [
            'area_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'area_name_ar' => ['required', 'string', 'max:45'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
            'area_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);
    }
}