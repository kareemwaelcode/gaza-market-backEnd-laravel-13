<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use App\Support\ApiCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class PublicProductController extends Controller
{
private const PER_PAGE = 20;

public function store(StoreProductRequest $request): JsonResponse
{
$storedPath = null;

try {
    $product = new Product;
    $product->fill($request->safe()->except('product_receipt_image'));
    $product->product_status = Product::STATUS_PENDING;
    $product->user_id = $request->user()->id;

    if ($request->hasFile('product_receipt_image')) {
        $path = $request->file('product_receipt_image')->store('receipts', 'local');

        if ($path === false) {
            throw new RuntimeException('Failed to store product receipt image');
        }

        $storedPath = $path;
        $product->product_receipt_image = $path;
    }

    $product->save();
} catch (Throwable $e) {
    if ($storedPath !== null) {
        Storage::disk('local')->delete($storedPath);
    }

    report($e);

    return response()->json(['icon' => 'error', 'title' => 'Could not save the price'], 500);
}

return response()->json([
    'data' => [
        'id' => $product->id,
        'status' => $product->product_status,
    ],
], 201);
}

public function search(Request $request): JsonResponse
{
$validator = validator($request->query(), [
    'q' => ['required', 'string', 'min:2', 'max:50'],
]);

if ($validator->fails()) {
    return response()->json([
        'icon' => 'error',
        'title' => $validator->getMessageBag()->first(),
    ], 400);
}

// توحيد الكلمة: نفس النتيجة لـ " Sugar" و"sugar" (الـ collation بالداتابيز utf8mb4_unicode_ci)
$term = mb_strtolower(trim((string) $request->query('q')));

$pageNumber = Paginator::resolveCurrentPage();
$build = fn () => $this->searchPayload($term);

$payload = $pageNumber > (int) config('prices.cache.max_page')
    ? $build()
    : ApiCache::remember(
        [ApiCache::PRODUCTS],
        'search:'.md5(json_encode([$term, $pageNumber])),
        (int) config('prices.cache.search_ttl'),
        $build,
        fn (array $value) => $value['meta']['total'] === 0,
    );

return response()->json($payload);
}

/** @return array{data: array, meta: array} */
private function searchPayload(string $term): array
{
$representatives = Product::query()
    ->active()
    ->search($term)
    ->selectRaw('min(id)')
    ->groupBy('product_name_ar', 'product_name_en', 'product_quantity', 'unit_id');

$page = Product::query()
    ->whereIn('id', $representatives)
    ->with('unit:id,unit_name_ar,unit_name_en')
    ->orderByDesc('id')
    ->paginate(self::PER_PAGE)
    ->appends(['q' => $term]);

$items = $page->getCollection()->map(fn (Product $product) => [
    'id' => $product->id,
    'name_ar' => $product->product_name_ar,
    'name_en' => $product->product_name_en,
    'quantity' => $product->product_quantity,
    'unit' => $product->unit ? [
        'name_ar' => $product->unit->unit_name_ar,
        'name_en' => $product->unit->unit_name_en,
    ] : null,
])->values();

return [
    'data' => $items->all(),
    'meta' => [
        'current_page' => $page->currentPage(),
        'last_page' => $page->lastPage(),
        'per_page' => $page->perPage(),
        'total' => $page->total(),
    ],
];
}
}