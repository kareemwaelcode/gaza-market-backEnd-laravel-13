<?php

namespace App\Http\Controllers\Api;

use App\Enums\StoreCategoryVariant;
use App\Http\Controllers\Api\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\StoreCategory;
use App\Support\ApiCache;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Validation\Rule;

class StoreCategoryController extends Controller implements HasMiddleware
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
        $storeCategories = ApiCache::lookup('store-categories', fn () => StoreCategory::with('storeCategoryGroup:id,store_category_group_name_en')->ordered()->paginate(10)->toArray());

        return response()->json([
            'status' => true,
            'message' => 'Data of Store Categories',
            'data' => $storeCategories,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->storeCategoryValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        StoreCategory::create([
            'store_category_slug' => $this->uniqueSlug(StoreCategory::class, 'store_category_slug', $request->input('store_category_name_en')),
            'store_category_variant_key' => $request->input('store_category_variant_key'),
            'store_category_name_en' => $request->input('store_category_name_en'),
            'store_category_name_ar' => $request->input('store_category_name_ar'),
            'store_category_is_active' => $request->boolean('store_category_is_active'),
            'store_category_sort_order' => $request->input('store_category_sort_order'),
            'store_category_group_id' => $request->input('store_category_group_id'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Created Successfully',
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $storeCategory = StoreCategory::with('storeCategoryGroup:id,store_category_group_name_en')->findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Store Category',
            'data' => $storeCategory,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $storeCategory = StoreCategory::findOrFail($id);

        $validator = $this->storeCategoryValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $storeCategory->update([
            'store_category_variant_key' => $request->input('store_category_variant_key'),
            'store_category_name_en' => $request->input('store_category_name_en'),
            'store_category_name_ar' => $request->input('store_category_name_ar'),
            'store_category_is_active' => $request->boolean('store_category_is_active'),
            'store_category_sort_order' => $request->input('store_category_sort_order'),
            'store_category_group_id' => $request->input('store_category_group_id'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $storeCategory = StoreCategory::findOrFail($id);

        try {
            $storeCategory->delete();
        } catch (QueryException $e) {
            return response()->json([
                'icon' => 'error',
                'title' => 'Cannot delete a store category that is used by places',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function storeCategoryValidator(Request $request): ValidatorContract
    {
        return validator($request->all(), [
            'store_category_variant_key' => ['required', Rule::enum(StoreCategoryVariant::class)],
            'store_category_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'store_category_name_ar' => ['required', 'string', 'max:45'],
            'store_category_is_active' => ['required', 'boolean'],
            'store_category_sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
            'store_category_group_id' => ['required', 'integer', 'exists:store_category_groups,id'],
        ]);
    }
}