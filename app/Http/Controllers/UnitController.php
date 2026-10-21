<?php

namespace App\Http\Controllers;

use App\Models\Unit;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class UnitController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $units = Unit::ordered()->paginate(10);
        return response()->view('cms.unit.index', compact('units'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->view('cms.unit.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'unit_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'unit_name_ar' => ['required', 'string', 'max:45'],
            'unit_is_active' => ['required', 'boolean'],
            'unit_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        if (! $validator->fails()) {
            $unit = new Unit();
            $unit->unit_name_en = $request->input('unit_name_en');
            $unit->unit_name_ar = $request->input('unit_name_ar');
            $unit->unit_is_active = $request->boolean('unit_is_active');
            $unit->unit_sort_order = $request->input('unit_sort_order');
            // الـ slug بيتولد تلقائي من الاسم الإنجليزي ولا يتغير بعد الإنشاء
            $unit->unit_slug = $this->generateUniqueSlug($request->input('unit_name_en'));
            $isSaved = $unit->save();

            if ($isSaved) {
                return response()->json(['icon' => 'success', 'title' => 'Created is Successfully'], 200);
            } else {
                return response()->json(['icon' => 'error', 'title' => 'Created is Failed'], 400);
            }
        } else {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        $unit = Unit::findOrFail($id);
        return response()->view('cms.unit.edit', compact('unit'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'unit_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'unit_name_ar' => ['required', 'string', 'max:45'],
            'unit_is_active' => ['required', 'boolean'],
            'unit_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        if (! $validator->fails()) {
            $unit = Unit::findOrFail($id);
            $unit->unit_name_en = $request->input('unit_name_en');
            $unit->unit_name_ar = $request->input('unit_name_ar');
            $unit->unit_is_active = $request->boolean('unit_is_active');
            $unit->unit_sort_order = $request->input('unit_sort_order');
            // unit_slug ما بيتغير عند التعديل عشان ما تنكسر الروابط
            $isUpdated = $unit->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('unit.index')], 200);
            } else {
                return response()->json(['icon' => 'error', 'title' => 'Updated is Failed'], 400);
            }
        } else {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        Unit::destroy($id);
        return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
    }

    /* ---------------- Helpers ---------------- */

    private function generateUniqueSlug(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name), 40, ''), '-');
        $slug = $base;
        $i = 2;

        while (Unit::where('unit_slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}