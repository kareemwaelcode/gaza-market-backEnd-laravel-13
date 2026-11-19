<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\Unit;
use App\Support\ApiCache;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class UnitController extends Controller implements HasMiddleware
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
        $units = ApiCache::lookup('units', fn () => Unit::ordered()->paginate(10)->toArray());

        return response()->json([
            'status' => true,
            'message' => 'Data of Units',
            'data' => $units,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->unitValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        Unit::create([
            'unit_slug' => $this->uniqueSlug(Unit::class, 'unit_slug', $request->input('unit_name_en')),
            'unit_name_en' => $request->input('unit_name_en'),
            'unit_name_ar' => $request->input('unit_name_ar'),
            'unit_is_active' => $request->boolean('unit_is_active'),
            'unit_sort_order' => $request->input('unit_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Created Successfully',
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Unit',
            'data' => $unit,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);

        $validator = $this->unitValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $unit->update([
            'unit_name_en' => $request->input('unit_name_en'),
            'unit_name_ar' => $request->input('unit_name_ar'),
            'unit_is_active' => $request->boolean('unit_is_active'),
            'unit_sort_order' => $request->input('unit_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $unit = Unit::findOrFail($id);

        try {
            $unit->delete();
        } catch (QueryException $e) {
            return response()->json([
                'icon' => 'error',
                'title' => 'Cannot delete a unit that is in use',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function unitValidator(Request $request): ValidatorContract
    {
        return validator($request->all(), [
            'unit_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'unit_name_ar' => ['required', 'string', 'max:45'],
            'unit_is_active' => ['required', 'boolean'],
            'unit_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);
    }
}