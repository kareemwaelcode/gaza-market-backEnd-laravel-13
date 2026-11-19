<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\CategoryGroup;
use App\Support\ApiCache;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CategoryGroupController extends Controller implements HasMiddleware
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
        $categoryGroups = ApiCache::lookup('category-groups', fn () => CategoryGroup::ordered()->paginate(10)->toArray());

        return response()->json([
            'status' => true,
            'message' => 'Data of Category Groups',
            'data' => $categoryGroups,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->categoryGroupValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        CategoryGroup::create([
            'category_group_slug' => $this->uniqueSlug(CategoryGroup::class, 'category_group_slug', $request->input('category_group_name_en')),
            'category_group_name_en' => $request->input('category_group_name_en'),
            'category_group_name_ar' => $request->input('category_group_name_ar'),
            'category_group_sort_order' => $request->input('category_group_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Created Successfully',
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $categoryGroup = CategoryGroup::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Category Group',
            'data' => $categoryGroup,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $categoryGroup = CategoryGroup::findOrFail($id);

        $validator = $this->categoryGroupValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $categoryGroup->update([
            'category_group_name_en' => $request->input('category_group_name_en'),
            'category_group_name_ar' => $request->input('category_group_name_ar'),
            'category_group_sort_order' => $request->input('category_group_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $categoryGroup = CategoryGroup::findOrFail($id);

        try {
            $categoryGroup->delete();
        } catch (QueryException $e) {
            return response()->json([
                'icon' => 'error',
                'title' => 'Cannot delete a category group that has categories',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function categoryGroupValidator(Request $request): ValidatorContract
    {
        return validator($request->all(), [
            'category_group_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'category_group_name_ar' => ['required', 'string', 'max:45'],
            'category_group_sort_order' => ['nullable', 'integer', 'min:0', 'max:127'],
        ]);
    }
}