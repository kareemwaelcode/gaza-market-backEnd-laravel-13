<?php

namespace App\Http\Controllers;

use App\Models\PointLevel;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PointLevelController extends Controller
{
    public function index()
    {
        $pointLevels = PointLevel::ordered()->paginate(10);
        return response()->view('cms.point_level.index', compact('pointLevels'));
    }

    public function edit(string $id)
    {
        $pointLevel = PointLevel::findOrFail($id);
        return response()->view('cms.point_level.edit', compact('pointLevel'));
    }

    /**
     * point_level_key is read-only: any value sent for it is ignored.
     * Levels are inserted once via SQL and are never created or deleted from the CMS.
     */
    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'point_level_name_en' => ['required', 'string', 'max:100', 'regex:/[A-Za-z0-9]/'],
            'point_level_name_ar' => ['required', 'string', 'max:100'],
            'point_level_min_points' => ['required', 'integer', 'min:0', 'max:4294967295',
            Rule::unique('point_levels', 'point_level_min_points')->ignore($id)],
            'point_level_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        if (! $validator->fails()) {
            $level = PointLevel::findOrFail($id);

            // لازم يضل في مستوى حده الأدنى 0، وإلا المستخدم الجديد بدون مستوى
            if ($level->point_level_min_points === 0 && (int) $request->input('point_level_min_points') !== 0) {
                return response()->json(['icon' => 'error', 'title' => 'The base level must keep min points = 0'], 400);
            }

            $level->point_level_name_en = $request->input('point_level_name_en');
            $level->point_level_name_ar = $request->input('point_level_name_ar');
            $level->point_level_min_points = (int) $request->input('point_level_min_points');
            $level->point_level_sort_order = $request->filled('point_level_sort_order')
                ? (int) $request->input('point_level_sort_order')
                : 0;
            $isUpdated = $level->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('point_level.index')], 200);
            } else {
                return response()->json(['icon' => 'error', 'title' => 'Updated is Failed'], 400);
            }
        } else {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }
    }
}