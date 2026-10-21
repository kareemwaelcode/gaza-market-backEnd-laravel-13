<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryGroup;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with('categoryGroup:id,category_group_name_en')->ordered()->paginate(10);
        return response()->view('cms.category.index', compact('categories'));
    }

    public function create()
    {
        $category_groups = CategoryGroup::ordered()->get(['id', 'category_group_name_en']);
        return response()->view('cms.category.create', compact('category_groups'));
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), [
            'category_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'category_name_ar' => ['required', 'string', 'max:45'],
            'category_is_active' => ['required', 'boolean'],
            'category_sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'category_group_id' => ['required', 'integer', 'exists:category_groups,id'],
        ]);

        if (! $validator->fails()) {
            $category = new Category();
            $category->category_name_en = $request->input('category_name_en');
            $category->category_name_ar = $request->input('category_name_ar');
            $category->category_is_active = $request->boolean('category_is_active');
            $category->category_sort_order = $request->input('category_sort_order');
            $category->category_group_id = $request->input('category_group_id');
            $category->category_slug = $this->generateUniqueSlug($request->input('category_name_en'));
            $isSaved = $category->save();

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
        $category = Category::findOrFail($id);
        $category_groups = CategoryGroup::ordered()->get(['id', 'category_group_name_en']);
        return response()->view('cms.category.edit', compact('category', 'category_groups'));
    }

    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), [
            'category_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'category_name_ar' => ['required', 'string', 'max:45'],
            'category_is_active' => ['required', 'boolean'],
            'category_sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'category_group_id' => ['required', 'integer', 'exists:category_groups,id'],
        ]);

        if (! $validator->fails()) {
            $category = Category::findOrFail($id);
            $category->category_name_en = $request->input('category_name_en');
            $category->category_name_ar = $request->input('category_name_ar');
            $category->category_is_active = $request->boolean('category_is_active');
            $category->category_sort_order = $request->input('category_sort_order');
            $category->category_group_id = $request->input('category_group_id');
            $isUpdated = $category->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('category.index')], 200);
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
            Category::destroy($id);
        } catch (QueryException $e) {
            return response()->json(['icon' => 'error', 'title' => 'Cannot delete a category that has products'], 409);
        }

        return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name), 40, ''), '-');
        $slug = $base;
        $i = 2;

        while (Category::where('category_slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}