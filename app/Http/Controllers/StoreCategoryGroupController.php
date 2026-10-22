<?php

namespace App\Http\Controllers;

use App\Models\StoreCategoryGroup;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class StoreCategoryGroupController extends Controller
{
    public function index()
    {
        $store_category_groups = StoreCategoryGroup::ordered()->paginate(10);
        return response()->view('cms.store_category_group.index', compact('store_category_groups'));
    }

    public function create()
    {
        return response()->view('cms.store_category_group.create');
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'store_category_group_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'store_category_group_name_ar' => ['required', 'string', 'max:45'],
            'store_category_group_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        if (! $validator->fails()) {
            $store_category_group = new StoreCategoryGroup();
            $store_category_group->store_category_group_name_en = $request->input('store_category_group_name_en');
            $store_category_group->store_category_group_name_ar = $request->input('store_category_group_name_ar');
            $store_category_group->store_category_group_sort_order = $request->integer('store_category_group_sort_order');
            $store_category_group->store_category_group_slug = $this->generateUniqueSlug($request->input('store_category_group_name_en'));
            $isSaved = $store_category_group->save();

            if ($isSaved) {
                return response()->json(['icon' => 'success', 'title' => 'Created is Successfully'], 200);
            } else {
                return response()->json(['icon' => 'error', 'title' => 'Created is Failed'], 400);
            }
        } else {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }
    }

    public function edit(string $id)
    {
        $store_category_group = StoreCategoryGroup::findOrFail($id);
        return response()->view('cms.store_category_group.edit', compact('store_category_group'));
    }

    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'store_category_group_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'store_category_group_name_ar' => ['required', 'string', 'max:45'],
            'store_category_group_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        if (! $validator->fails()) {
            $store_category_group = StoreCategoryGroup::findOrFail($id);
            $store_category_group->store_category_group_name_en = $request->input('store_category_group_name_en');
            $store_category_group->store_category_group_name_ar = $request->input('store_category_group_name_ar');
            $store_category_group->store_category_group_sort_order = $request->integer('store_category_group_sort_order');
            $isUpdated = $store_category_group->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('store_category_group.index')], 200);
            } else {
                return response()->json(['icon' => 'error', 'title' => 'Updated is Failed'], 400);
            }
        } else {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }
    }

    public function destroy(string $id)
    {
        try {
            StoreCategoryGroup::destroy($id);
        } catch (QueryException $e) {
            return response()->json(['icon' => 'error', 'title' => 'Cannot delete a store category group that has store categories'], 409);
        }

        return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name), 40, ''), '-');
        $slug = $base;
        $i = 2;

        while (StoreCategoryGroup::where('store_category_group_slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}