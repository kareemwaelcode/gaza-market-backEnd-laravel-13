<?php

namespace App\Http\Controllers;

use App\Models\CategoryGroup;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryGroupController extends Controller
{
    public function index()
    {
        $category_groups = CategoryGroup::ordered()->paginate(10);
        return response()->view('cms.category_group.index', compact('category_groups'));
    }

    public function create()
    {
        return response()->view('cms.category_group.create');
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'category_group_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'category_group_name_ar' => ['required', 'string', 'max:45'],
            'category_group_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        if (! $validator->fails()) {
            $category_group = new CategoryGroup();
            $category_group->category_group_name_en = $request->input('category_group_name_en');
            $category_group->category_group_name_ar = $request->input('category_group_name_ar');
            $category_group->category_group_sort_order = $request->input('category_group_sort_order');
            $category_group->category_group_slug = $this->generateUniqueSlug($request->input('category_group_name_en'));
            $isSaved = $category_group->save();

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
        $category_group = CategoryGroup::findOrFail($id);
        return response()->view('cms.category_group.edit', compact('category_group'));
    }

    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'category_group_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'category_group_name_ar' => ['required', 'string', 'max:45'],
            'category_group_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);

        if (! $validator->fails()) {
            $category_group = CategoryGroup::findOrFail($id);
            $category_group->category_group_name_en = $request->input('category_group_name_en');
            $category_group->category_group_name_ar = $request->input('category_group_name_ar');
            $category_group->category_group_sort_order = $request->input('category_group_sort_order');
            $isUpdated = $category_group->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('category_group.index')], 200);
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
            CategoryGroup::destroy($id);
        } catch (QueryException $e) {
            return response()->json(['icon' => 'error', 'title' => 'Cannot delete a category group that has categories'], 409);
        }

        return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name), 40, ''), '-');
        $slug = $base;
        $i = 2;

        while (CategoryGroup::where('category_group_slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}