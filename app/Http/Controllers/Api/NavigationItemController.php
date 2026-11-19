<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\GeneratesUniqueSlug;
use App\Http\Controllers\Controller;
use App\Models\NavigationItem;
use App\Support\ApiCache;
use Illuminate\Contracts\Validation\Validator as ValidatorContract;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class NavigationItemController extends Controller implements HasMiddleware
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
        $navigationItems = ApiCache::lookup('navigation-items', fn () => NavigationItem::ordered()->paginate(10)->toArray());

        return response()->json([
            'status' => true,
            'message' => 'Data of Navigation Items',
            'data' => $navigationItems,
        ], 200);
    }

    public function store(Request $request): JsonResponse
    {
        $validator = $this->navigationItemValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $attempts = 3;

        for ($i = 1; $i <= $attempts; $i++) {
            try {
                NavigationItem::create($this->payload($request) + [
                    'navigation_item_slug' => $this->uniqueSlug(
                        NavigationItem::class,
                        'navigation_item_slug',
                        $request->input('navigation_item_name_en')
                    ),
                ]);

                break;
            } catch (UniqueConstraintViolationException $e) {
                if ($i === $attempts) {
                    throw $e;
                }
            }
        }

        return response()->json([
            'icon' => 'success',
            'title' => 'Created Successfully',
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $navigationItem = NavigationItem::findOrFail($id);

        return response()->json([
            'status' => true,
            'message' => 'Data of Navigation Item',
            'data' => $navigationItem,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $navigationItem = NavigationItem::findOrFail($id);

        $validator = $this->navigationItemValidator($request);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $navigationItem->update($this->payload($request));

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        NavigationItem::findOrFail($id)->delete();

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function navigationItemValidator(Request $request): ValidatorContract
    {
        return validator($request->all(), [
            'navigation_item_name_en' => ['required', 'string', 'max:45', 'regex:/[A-Za-z0-9]/'],
            'navigation_item_name_ar' => ['required', 'string', 'max:45'],
            'navigation_item_icon' => ['required', 'string', 'max:45', 'regex:/^[a-z0-9-]+$/'],
            'navigation_item_url' => ['nullable', 'string', 'max:255', 'regex:/^(#|https?:\/\/\S+|(?!\/\/)[A-Za-z0-9_\-\/\.]+(\?\S*)?(#\S*)?)$/'],
            'navigation_item_sort_order' => ['nullable', 'integer', 'min:0', 'max:255'],
            'navigation_item_is_active' => ['required', 'boolean'],
            'navigation_item_is_mobile' => ['required', 'boolean'],
            'navigation_item_is_desktop' => ['required', 'boolean'],
            'navigation_item_is_coming_soon' => ['required', 'boolean'],
        ]);
    }

    private function payload(Request $request): array
    {
        return [
            'navigation_item_name_en' => $request->input('navigation_item_name_en'),
            'navigation_item_name_ar' => $request->input('navigation_item_name_ar'),
            'navigation_item_icon' => $request->input('navigation_item_icon'),
            'navigation_item_url' => $request->filled('navigation_item_url') ? $request->input('navigation_item_url') : null,
            'navigation_item_sort_order' => $request->filled('navigation_item_sort_order') ? (int) $request->input('navigation_item_sort_order') : 0,
            'navigation_item_is_active' => $request->boolean('navigation_item_is_active'),
            'navigation_item_is_mobile' => $request->boolean('navigation_item_is_mobile'),
            'navigation_item_is_desktop' => $request->boolean('navigation_item_is_desktop'),
            'navigation_item_is_coming_soon' => $request->boolean('navigation_item_is_coming_soon'),
        ];
    }
}