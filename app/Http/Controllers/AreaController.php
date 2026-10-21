<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Regions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AreaController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $areas = Area::with('region:id,region_name_en')->ordered()->paginate(10);
        return response()->view('cms.area.index', compact('areas'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $regions = Regions::ordered()->get(['id', 'region_name_en']);
        return response()->view('cms.area.create', compact('regions'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'area_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'area_name_ar' => ['required', 'string', 'max:45'],
            'area_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
        ]);

        if (! $validator->fails()) {
            $area = new Area();
            $area->area_name_en = $request->input('area_name_en');
            $area->area_name_ar = $request->input('area_name_ar');
            $area->area_sort_order = $request->input('area_sort_order');
            $area->region_id = $request->input('region_id');
            $area->area_slug = $this->generateUniqueSlug($request->input('area_name_en'));
            $isSaved = $area->save();

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
        $area = Area::findOrFail($id);
        $regions = Regions::ordered()->get(['id', 'region_name_en']);
        return response()->view('cms.area.edit', compact('area', 'regions'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'area_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'area_name_ar' => ['required', 'string', 'max:45'],
            'area_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
            'region_id' => ['required', 'integer', 'exists:regions,id'],
        ]);

        if (! $validator->fails()) {
            $area = Area::findOrFail($id);
            $area->area_name_en = $request->input('area_name_en');
            $area->area_name_ar = $request->input('area_name_ar');
            $area->area_sort_order = $request->input('area_sort_order');
            $area->region_id = $request->input('region_id');
            $isUpdated = $area->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('area.index')], 200);
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
        Area::destroy($id);
        return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
    }
    private function generateUniqueSlug(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name), 40, ''), '-');
        $slug = $base;
        $i = 2;

        while (Area::where('area_slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}