<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\Regions;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RegionController extends Controller
{
    use GeneratesUniqueSlug;

    public function index(): JsonResponse
    {
        $regions = Regions::ordered()->paginate(5);

        return response()->json([
            'status' => true,
            'message' => 'Data of Regions',
            'data' => $regions,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->regionValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        Regions::create([
            'region_slug' => $this->uniqueSlug(Regions::class, 'region_slug', $request->input('region_name_en')),
            'region_name_en' => $request->input('region_name_en'),
            'region_name_ar' => $request->input('region_name_ar'),
            'region_sort_order' => $request->input('region_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Created Successfully',
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $region = Regions::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Region',
            'data' => $region,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $region = Regions::findOrFail($id);

        $validator = $this->regionValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $region->update([
            'region_name_en' => $request->input('region_name_en'),
            'region_name_ar' => $request->input('region_name_ar'),
            'region_sort_order' => $request->input('region_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $region = Regions::findOrFail($id);

        try {
            $region->delete();
        } catch (QueryException $e) {
            return response()->json([
                'icon' => 'error',
                'title' => 'Cannot delete a region that has areas',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function regionValidator(Request $request): ValidatorContract
    {
        return validator($request->all(), [
            'region_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'region_name_ar' => ['required', 'string', 'max:45'],
            'region_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);
    }
}