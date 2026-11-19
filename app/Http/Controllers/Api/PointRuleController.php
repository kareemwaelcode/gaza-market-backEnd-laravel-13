<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PointRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class PointRuleController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('auth:admins-api', except: ['index', 'show']),
        ];
    }

    public function index(): JsonResponse
    {
        $pointRules = PointRule::ordered()->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Data of Point Rules',
            'data' => $pointRules,
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $pointRule = PointRule::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Point Rule',
            'data' => $pointRule,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $pointRule = PointRule::findOrFail($id);

        $validator = validator($request->all(), [
            'point_rule_name_en' => ['required', 'string', 'max:100', 'regex:/[A-Za-z0-9]/'],
            'point_rule_name_ar' => ['required', 'string', 'max:100'],
            'point_rule_points' => ['required', 'integer', 'between:-32768,32767'],
            'point_rule_daily_limit' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'point_rule_is_active' => ['required', 'boolean'],
            'point_rule_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $pointRule->update([
            'point_rule_name_en' => $request->input('point_rule_name_en'),
            'point_rule_name_ar' => $request->input('point_rule_name_ar'),
            'point_rule_points' => (int) $request->input('point_rule_points'),
            'point_rule_daily_limit' => $request->filled('point_rule_daily_limit')
                ? (int) $request->input('point_rule_daily_limit')
                : null,
            'point_rule_is_active' => $request->boolean('point_rule_is_active'),
            'point_rule_sort_order' => $request->filled('point_rule_sort_order')
                ? (int) $request->input('point_rule_sort_order')
                : 0,
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }
}