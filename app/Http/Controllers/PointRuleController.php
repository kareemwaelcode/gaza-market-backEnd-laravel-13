<?php

namespace App\Http\Controllers;

use App\Models\PointRule;
use Illuminate\Http\Request;

class PointRuleController extends Controller
{
    public function index()
    {
        $pointRules = PointRule::ordered()->paginate(10);
        return response()->view('cms.point_rule.index', compact('pointRules'));
    }

    public function edit(string $id)
    {
        $pointRule = PointRule::findOrFail($id);
        return response()->view('cms.point_rule.edit', compact('pointRule'));
    }

    /**
     * point_rule_key is read-only: any value sent for it is ignored.
     * Rules are created by the seeder and are disabled via is_active, never deleted.
     */
    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'point_rule_name_en' => ['required', 'string', 'max:100', 'regex:/[A-Za-z0-9]/'],
            'point_rule_name_ar' => ['required', 'string', 'max:100'],
            'point_rule_points' => ['required', 'integer', 'between:-32768,32767'],
            'point_rule_daily_limit' => ['nullable', 'integer', 'min:1', 'max:65535'],
            'point_rule_is_active' => ['required', 'boolean'],
            'point_rule_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        if (! $validator->fails()) {
            $rule = PointRule::findOrFail($id);
            $rule->point_rule_name_en = $request->input('point_rule_name_en');
            $rule->point_rule_name_ar = $request->input('point_rule_name_ar');
            $rule->point_rule_points = (int) $request->input('point_rule_points');
            $rule->point_rule_daily_limit = $request->filled('point_rule_daily_limit')
                ? (int) $request->input('point_rule_daily_limit')
                : null;
            $rule->point_rule_is_active = $request->boolean('point_rule_is_active');
            $rule->point_rule_sort_order = $request->filled('point_rule_sort_order')
                ? (int) $request->input('point_rule_sort_order')
                : 0;
            $isUpdated = $rule->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('point_rule.index')], 200);
            } else {
                return response()->json(['icon' => 'error', 'title' => 'Updated is Failed'], 400);
            }
        } else {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }
    }
}