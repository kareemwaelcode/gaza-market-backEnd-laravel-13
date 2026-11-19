<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointLevel;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class PointLevelController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:admins-api', except: ['index', 'show']),
        ];
    }

    public function index(): JsonResponse
    {
        $pointLevels = ApiCache::lookup('point-levels', fn () => PointLevel::ordered()->paginate(10)->toArray());

        return response()->json([
            'status' => true,
            'message' => 'Data of Point Levels',
            'data' => $pointLevels,
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $pointLevel = PointLevel::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Point Level',
            'data' => $pointLevel,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $pointLevel = PointLevel::findOrFail($id);

        $validator = validator($request->all(), [
            'point_level_name_en' => ['required', 'string', 'max:100', 'regex:/[A-Za-z0-9]/'],
            'point_level_name_ar' => ['required', 'string', 'max:100'],
            'point_level_min_points' => [
                'required',
                'integer',
                'min:0',
                'max:4294967295',
                Rule::unique('point_levels', 'point_level_min_points')->ignore($id),
            ],
            'point_level_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        if ($pointLevel->point_level_min_points === 0 && (int) $request->input('point_level_min_points') !== 0) {
            return response()->json([
                'icon' => 'error',
                'title' => 'The base level must keep min points = 0',
            ], 400);
        }

        $pointLevel->update([
            'point_level_name_en' => $request->input('point_level_name_en'),
            'point_level_name_ar' => $request->input('point_level_name_ar'),
            'point_level_min_points' => (int) $request->input('point_level_min_points'),
            'point_level_sort_order' => $request->filled('point_level_sort_order')
                ? (int) $request->input('point_level_sort_order')
                : 0,
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }
}