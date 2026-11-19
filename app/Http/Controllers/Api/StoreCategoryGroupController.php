<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\StoreCategoryGroup;
use App\Support\ApiCache;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class StoreCategoryGroupController extends Controller implements HasMiddleware
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
        $storeCategoryGroups = ApiCache::lookup('store-category-groups', fn () => StoreCategoryGroup::ordered()->paginate(10)->toArray());

        return response()->json([
            'status' => true,
            'message' => 'Data of Store Category Groups',
            'data' => $storeCategoryGroups,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->storeCategoryGroupValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        StoreCategoryGroup::create([
            'store_category_group_slug' => $this->uniqueSlug(StoreCategoryGroup::class, 'store_category_group_slug', $request->input('store_category_group_name_en')),
            'store_category_group_name_en' => $request->input('store_category_group_name_en'),
            'store_category_group_name_ar' => $request->input('store_category_group_name_ar'),
            'store_category_group_sort_order' => $request->integer('store_category_group_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Created Successfully',
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $storeCategoryGroup = StoreCategoryGroup::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Store Category Group',
            'data' => $storeCategoryGroup,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $storeCategoryGroup = StoreCategoryGroup::findOrFail($id);

        $validator = $this->storeCategoryGroupValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $storeCategoryGroup->update([
            'store_category_group_name_en' => $request->input('store_category_group_name_en'),
            'store_category_group_name_ar' => $request->input('store_category_group_name_ar'),
            'store_category_group_sort_order' => $request->integer('store_category_group_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $storeCategoryGroup = StoreCategoryGroup::findOrFail($id);

        try {
            $storeCategoryGroup->delete();
        } catch (QueryException $e) {
            return response()->json([
                'icon' => 'error',
                'title' => 'Cannot delete a store category group that has store categories',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function storeCategoryGroupValidator(Request $request): ValidatorContract
    {
        return validator($request->all(), [
            'store_category_group_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'store_category_group_name_ar' => ['required', 'string', 'max:45'],
            'store_category_group_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);
    }
}