<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProductRequest;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class ProductController extends Controller
{
private const PER_PAGE = 20;

// تسجيل سعر جديد: صاحب السعر من المستخدم المسجّل، والحالة pending لحد موافقة الأدمن
public function store(StoreProductRequest $request): JsonResponse
{
$storedPath = null;

try {
    $product = new Product;
    $product->fill($request->safe()->except('product_receipt_image'));
    $product->product_status = Product::STATUS_PENDING;
    $product->user_id = $request->user()->id;

    // الفاتورة على القرص الخاص (local) مش public: الأدمن بس بشوفها من لوحة التحكم
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

// البحث بالاسم فقط (عربي أو إنجليزي): المعتمد فقط، الأحدث أولاً
// البحث بالاسم فقط (عربي أو إنجليزي): المعتمد فقط، والمنتج بحجمه (الكمية + الوحدة) مرة وحدة بدون تكرار.
// كل إرسال بينعمل صف بجدول products، فنجمّع الصفوف الفعّالة المتطابقة ونرجّع أقدمها كممثّل (id هاد اللي بينبعت لـ api/prices).
// ما في أسعار ولا متاجر ولا مناطق هون: الأسعار من api/prices.
// البحث بالاسم فقط (عربي أو إنجليزي): المعتمد فقط، والمنتج بحجمه (الكمية + الوحدة) مرة وحدة بدون تكرار.
// كل إرسال بينعمل صف بجدول products، فنجمّع الصفوف الفعّالة المتطابقة ونرجّع أقدمها كممثّل (id هاد اللي بينبعت لـ api/prices).
// ما في أسعار ولا متاجر ولا مناطق هون: الأسعار من api/prices.
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

$term = (string) $request->query('q');

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

return response()->json([
    'data' => $items,
    'meta' => [
        'current_page' => $page->currentPage(),
        'last_page' => $page->lastPage(),
        'per_page' => $page->perPage(),
        'total' => $page->total(),
    ],
]);
}
}