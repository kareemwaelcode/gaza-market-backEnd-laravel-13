<?php

namespace App\Http\Controllers;

use App\Models\Regions;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class RegionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $regions = Regions::ordered()->paginate(5);
        return response()->view('cms.region.index', compact('regions'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->view('cms.region.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'region_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'region_name_ar' => ['required', 'string', 'max:45'],
            'region_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);        

        if (! $validator->fails()) {
        $region = new Regions();
        $region->region_name_en = $request->input('region_name_en');
        $region->region_name_ar = $request->input('region_name_ar');
        $region->region_sort_order = $request->input('region_sort_order');
        $region->region_slug = $this->generateUniqueSlug($request->input('region_name_en'));
        $isSaved = $region->save();
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
        $region = Regions::findOrFail($id);
        return response()->view('cms.region.edit', compact('region'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'region_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'region_name_ar' => ['required', 'string', 'max:45'],
            'region_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        if (! $validator->fails()) {
            $region = Regions::findOrFail($id);
            $region->region_name_en = $request->input('region_name_en');
            $region->region_name_ar = $request->input('region_name_ar');
            $region->region_sort_order = $request->input('region_sort_order');
            $isUpdated = $region->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('region.index')], 200);
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
        Regions::destroy($id);
        return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name), 40, ''), '-');
        $slug = $base;
        $i = 2;

        while (Regions::where('region_slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }
}