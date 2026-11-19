<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class CategoryController extends Controller implements HasMiddleware
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
        $categories = Category::with('categoryGroup:id,category_group_name_en')->ordered()->paginate(10);

        return response()->json([
            'status' => true,
            'message' => 'Data of Categories',
            'data' => $categories,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->categoryValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        Category::create([
            'category_slug' => $this->uniqueSlug(Category::class, 'category_slug', $request->input('category_name_en')),
            'category_name_en' => $request->input('category_name_en'),
            'category_name_ar' => $request->input('category_name_ar'),
            'category_is_active' => $request->boolean('category_is_active'),
            'category_group_id' => $request->input('category_group_id'),
            'category_sort_order' => $request->input('category_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Created Successfully',
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Category',
            'data' => $category,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        $validator = $this->categoryValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $category->update([
            'category_name_en' => $request->input('category_name_en'),
            'category_name_ar' => $request->input('category_name_ar'),
            'category_is_active' => $request->boolean('category_is_active'),
            'category_group_id' => $request->input('category_group_id'),
            'category_sort_order' => $request->input('category_sort_order'),
        ]);

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $category = Category::findOrFail($id);

        try {
            $category->delete();
        } catch (QueryException $e) {
            return response()->json([
                'icon' => 'error',
                'title' => 'Cannot delete a category that has products',
            ], 409);
        }

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function categoryValidator(Request $request): ValidatorContract
    {
        return validator($request->all(), [
            'category_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'category_name_ar' => ['required', 'string', 'max:45'],
            'category_is_active' => ['required', 'boolean'],
            'category_group_id' => ['required', 'integer', 'exists:category_groups,id'],
            'category_sort_order' => ['nullable', 'integer', 'min:0', 'max:32767'],
        ]);
    }
}