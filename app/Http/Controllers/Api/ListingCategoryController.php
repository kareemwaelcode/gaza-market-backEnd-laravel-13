<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\ListingCategory;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ListingCategoryController extends Controller implements HasMiddleware
{
    use GeneratesUniqueSlug;

    public static function middleware(): array
    {
        return [
            new Middleware('auth:admins-api', except: ['index', 'show']),
        ];
    }

    public function index(): JsonResponse
    {
        $listingCategories = ListingCategory::ordered()->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Data of Listing Categories',
            'data' => $listingCategories,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->listingCategoryValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        ListingCategory::create([
            'listing_category_slug' => $this->uniqueSlug(ListingCategory::class, 'listing_category_slug', $request->input('listing_category_name_en')),
            'listing_category_name_en' => $request->input('listing_category_name_en'),
            'listing_category_name_ar' => $request->input('listing_category_name_ar'),
            'listing_category_is_active' => $request->boolean('listing_category_is_active'),
            'listing_category_sort_order' => $request->filled('listing_category_sort_order') ? (int) $request->input('listing_category_sort_order') : null,
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Created Successfully',
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $listingCategory = ListingCategory::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Listing Category',
            'data' => $listingCategory,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $listingCategory = ListingCategory::findOrFail($id);

        $validator = $this->listingCategoryValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $listingCategory->update([
            'listing_category_name_en' => $request->input('listing_category_name_en'),
            'listing_category_name_ar' => $request->input('listing_category_name_ar'),
            'listing_category_is_active' => $request->boolean('listing_category_is_active'),
            'listing_category_sort_order' => $request->filled('listing_category_sort_order') ? (int) $request->input('listing_category_sort_order') : null,
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $listingCategory = ListingCategory::findOrFail($id);

        try {
            $listingCategory->delete();
        } catch (QueryException $e) {
            return response()->json([
                'icon' => 'error',
                'title' => 'Cannot delete a listing category that has listings',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function listingCategoryValidator(Request $request): ValidatorContract
    {
        return validator($request->all(), [
            'listing_category_name_en' => ['required', 'string', 'max:100', 'regex:/[A-Za-z0-9]/'],
            'listing_category_name_ar' => ['required', 'string', 'max:100'],
            'listing_category_is_active' => ['required', 'boolean'],
            'listing_category_sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ]);
    }
}