<?php

namespace App\Http\Controllers;

use App\Models\ListingCategory;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ListingCategoryController extends Controller
{
    public function index()
    {
        $listingCategories = ListingCategory::ordered()->paginate(10);

        return response()->view('cms.listing_category.index', compact('listingCategories'));
    }

    public function create()
    {
        return response()->view('cms.listing_category.create');
    }

    /**
     * The slug is generated from the English name and never changes afterwards.
     */
    public function store(Request $request)
    {
        $validator = validator($request->all(), $this->rules());

        if (! $validator->fails()) {
            $listingCategory = new ListingCategory;
            $listingCategory->listing_category_name_en = $request->input('listing_category_name_en');
            $listingCategory->listing_category_name_ar = $request->input('listing_category_name_ar');
            $listingCategory->listing_category_is_active = $request->boolean('listing_category_is_active');
            $listingCategory->listing_category_sort_order = $request->filled('listing_category_sort_order')
                ? (int) $request->input('listing_category_sort_order')
                : null;
            $listingCategory->listing_category_slug = $this->generateUniqueSlug($request->input('listing_category_name_en'));
            $isSaved = $listingCategory->save();

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
        $listingCategory = ListingCategory::findOrFail($id);

        return response()->view('cms.listing_category.edit', compact('listingCategory'));
    }

    /**
     * listing_category_slug is read-only: any value sent for it is ignored.
     * Categories are normally disabled via is_active. Deleting is allowed only while
     * the category has no listings (see destroy: otherwise it answers 409).
     */
    public function update(Request $request, string $id)
    {
        $validator = validator($request->all(), $this->rules());

        if (! $validator->fails()) {
            $listingCategory = ListingCategory::findOrFail($id);
            $listingCategory->listing_category_name_en = $request->input('listing_category_name_en');
            $listingCategory->listing_category_name_ar = $request->input('listing_category_name_ar');
            $listingCategory->listing_category_is_active = $request->boolean('listing_category_is_active');
            $listingCategory->listing_category_sort_order = $request->filled('listing_category_sort_order')
                ? (int) $request->input('listing_category_sort_order')
                : null;
            $isUpdated = $listingCategory->save();

            if ($isUpdated) {
                return response()->json(['redirect' => route('listing_category.index')], 200);
            } else {
                return response()->json(['icon' => 'error', 'title' => 'Updated is Failed'], 400);
            }
        } else {
            return response()->json(['icon' => 'error', 'title' => $validator->getMessageBag()->first()], 400);
        }
    }

    public function destroy(string $id)
{
    $listingCategory = ListingCategory::findOrFail($id);

    try {
        $listingCategory->delete();
    } catch (QueryException $e) {
        return response()->json(['icon' => 'error', 'title' => 'Cannot delete a listing category that has listings'], 409);
    }

    return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
}

    private function rules(): array
    {
        return [
            'listing_category_name_en' => ['required', 'string', 'max:100', 'regex:/[A-Za-z0-9]/'],
            'listing_category_name_ar' => ['required', 'string', 'max:100'],
            'listing_category_is_active' => ['required', 'boolean'],
            'listing_category_sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ];
    }

    private function generateUniqueSlug(string $name): string
    {
        $base = rtrim(Str::limit(Str::slug($name), 40, ''), '-');
        $slug = $base;
        $i = 2;

        while (ListingCategory::where('listing_category_slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}