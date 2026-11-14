<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Support\PriceTicker;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductController extends Controller
{
public function index(Request $request)
{
$validator = validator($request->query(), [
    'status' => ['nullable', Rule::in(Product::STATUSES)],
    'category' => ['nullable', 'integer'],
    'area' => ['nullable', 'integer'],
]);

if ($validator->fails() && $request->expectsJson()) {
    throw new ValidationException($validator);
}

$ignoredFilters = $validator->errors()->keys();
$filters = collect($request->query())->only(['status', 'category', 'area'])->except($ignoredFilters)->all();

$products = Product::query()
    ->with([
        'user:id,user_full_name,user_phone',
        'category:id,category_name_ar,category_name_en',
        'unit:id,unit_name_ar,unit_name_en',
        'area:id,area_name_ar',
    ])
    ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('product_status', $status))
    ->when($filters['category'] ?? null, fn ($q, $id) => $q->where('category_id', $id))
    ->when($filters['area'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
    ->orderByRaw("case when product_status = 'pending' then 0 else 1 end")
    ->orderByDesc('id')
    ->paginate(15)
    ->appends($filters);

$categories = Category::ordered()->get(['id', 'category_name_ar', 'category_name_en']);
$areas = Area::ordered()->get(['id', 'area_name_ar']);

return response()->view('cms.product.index', compact('products', 'categories', 'areas', 'filters', 'ignoredFilters'));
}

public function show(string $id)
{
$product = Product::with(['user', 'category', 'unit', 'area.region'])->findOrFail($id);

return response()->view('cms.product.show', compact('product'));
}

public function receipt(string $id)
{
$product = Product::findOrFail($id);
$path = $product->product_receipt_image;

abort_unless($path && Storage::disk('local')->exists($path), 404);

return Storage::disk('local')->response($path);
}

public function approve(string $id)
{
$product = Product::findOrFail($id);

$approved = DB::transaction(function () use ($product) {
    $updated = Product::whereKey($product->id)
        ->where('product_status', Product::STATUS_PENDING)
        ->update([
            'product_status' => Product::STATUS_ACTIVE,
            'updated_at' => now(),
        ]);

    if (! $updated) {
        return false;
    }

    $product->refresh()->createFirstPrice();

    return true;
});

if (! $approved) {
    return response()->json(['icon' => 'error', 'title' => 'Only pending products can be approved'], 409);
}

PriceTicker::forget();

return response()->json(['redirect' => route('product.show', $product->id)], 200);
}

public function hide(string $id)
{
return $this->changeStatus($id, Product::STATUS_ACTIVE, Product::STATUS_HIDDEN, 'Only active products can be hidden');
}

public function unhide(string $id)
{
return $this->changeStatus($id, Product::STATUS_HIDDEN, Product::STATUS_ACTIVE, 'Only hidden products can be shown');
}

public function destroy(string $id)
{
$product = Product::findOrFail($id);

$priceReceipts = [];

try {
    // الأسعار (وأصواتها بالـ cascade) بتنحذف مع المنتج كوحدة وحدة
    $deleted = DB::transaction(function () use ($product, &$priceReceipts) {
        $priceReceipts = ProductPrice::query()
            ->where('product_id', $product->id)
            ->whereNotNull('product_price_receipt_image')
            ->pluck('product_price_receipt_image')
            ->all();

        ProductPrice::query()->where('product_id', $product->id)->delete();

        return Product::whereKey($product->id)->delete();
    });
} catch (QueryException $e) {
    if ($e->getCode() === '23000') {
        return response()->json(['icon' => 'error', 'title' => 'This product is still referenced and cannot be deleted'], 409);
    }

    throw $e;
}

if (! $deleted) {
    return response()->json(['icon' => 'error', 'title' => 'Product not found'], 404);
}

// الملفات بعد الـ commit: لو فشلت المعاملة ما منخسر أي وصل
$paths = array_filter(array_merge([$product->product_receipt_image], $priceReceipts));

foreach ($paths as $path) {
    Storage::disk('local')->delete($path);
}

PriceTicker::forget();

return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
}

private function changeStatus(string $id, string $from, string $to, string $error)
{
$product = Product::findOrFail($id);

$updated = Product::whereKey($product->id)
    ->where('product_status', $from)
    ->update([
        'product_status' => $to,
        'updated_at' => now(),
    ]);

if (! $updated) {
    return response()->json(['icon' => 'error', 'title' => $error], 409);
}

PriceTicker::forget();

return response()->json(['redirect' => route('product.show', $product->id)], 200);
}
}