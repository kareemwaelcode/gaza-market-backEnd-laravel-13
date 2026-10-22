<?php

namespace App\Http\Controllers;

use App\Enums\StoreCategoryVariant;
use App\Models\StoreCategory;
use App\Models\StoreCategoryGroup;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreCategoryController extends Controller
{
    public function index()
    {
        $store_categories = StoreCategory::with('storeCategoryGroup:id,store_category_group_name_en')->ordered()->paginate(10);
        return response()->view('cms.store_category.index', compact('store_categories'));
    }

    public function create()
    {
        $store_category_groups = StoreCategoryGroup::ordered()->get(['id', 'store_category_group_name_en']);
        $store_category_variants = StoreCategoryVariant::cases();
        return response()->view('cms.store_category.create', compact('store_category_groups', 'store_category_variants'));
    }

    public function store(Request $request)
    {
        $validator = validator($request->all(), $this->rules());

        if (! $validator->fails()) {
            $store_category = new StoreCategory();
            $store_category->store_category_variant_key = $request->input('store_category_variant_key');
            $store_category->store_category_name_en = $request->input('store_category_name_en');
            $store_category->store_category_name_ar = $request->input('store_category_name_ar');
            $store_category->store_category_is_active = $request->boolean('store_category_is_active');
            $store_category->store_category_sort_order = $request->input('store_category_sort_order');
            $store_category->store_category_group_id = $request->input('store_category_group_id');
            $store_category->store_category_slug = $this->generateUniqueSlug($request->input('store_category_name_en'));
            $isSaved = $store_category->save();

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
        $store_category = StoreCategory::findOrFail($id);
        $store_category_groups = StoreCategoryGroup::ordered()->get(['id', 'store_category_group_name_en']);
        $store_category_variants = StoreCategoryVariant::cases();
        return response()->view('cms.store_category.edit', compact('store_category', 'store_category_groups', 'store_category_variants'));
    }

    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), $this->rules());

        if (! $validator->fails()) {
            $store_category = StoreCategory::findOrFail($id);
            $store_category->store_category_variant_key = $request->input('store_category_variant_key');
            $store_category->store_category_name_en = $request->input('store_category_name_en');
            $store_category->store_category_name_ar = $request->input('store_category_name_ar');
            $store_category->store_category_is_active = $request->boolean('store_category_is_active');
            $store_category->store_category_sort_order = $request->input('store_category_sort_order');
            $store_category->store_category_group_id = $request->input('store_category_group_id');
            $isUpdated = $store_category->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('store_category.index')], 200);
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
            StoreCategory::destroy($id);
        } catch (QueryException $e) {
            return response()->json(['icon' => 'error', 'title' => 'Cannot delete a store category that is used by places'], 409);
        }

        return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
    }


    private function rules(): array
    {
        return [
            'store_category_variant_key' => ['required', Rule::enum(StoreCategoryVariant::class)],
            'store_category_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'store_category_name_ar' => ['required', 'string', 'max:45'],
            'store_category_is_active' => ['required', 'boolean'],
            'store_category_sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'store_category_group_id' => ['required', 'integer', 'exists:store_category_groups,id'],
        ];
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name), 40, ''), '-');
        $slug = $base;
        $i = 2;

        while (StoreCategory::where('store_category_slug', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }
}