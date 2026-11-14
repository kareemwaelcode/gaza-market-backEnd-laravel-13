<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Category;
use App\Models\PriceVote;
use App\Models\ProductPrice;
use App\Support\PriceTicker;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class ProductPriceController extends Controller
{

public function index(Request $request) {
$validator = validator($request->query(), [
    'status' => ['nullable', Rule::in(ProductPrice::STATUSES)],
    'category' => ['nullable', 'integer'],
    'area' => ['nullable', 'integer'],
]);

if ($validator->fails() && $request->expectsJson()) {
    throw new ValidationException($validator);
}
$ignoredFilters = $validator->errors()->keys();
$filters = collect($request->query())
    ->only(['status', 'category', 'area'])
    ->except($ignoredFilters)
    ->all();

$prices = ProductPrice::query()
    ->with([
        'user:id,user_full_name,user_phone',
        'product:id,product_name_ar,product_name_en,product_quantity,category_id,unit_id',
        'product.category:id,category_name_ar,category_name_en',
        'product.unit:id,unit_name_ar,unit_name_en',
        'area:id,area_name_ar',
    ])
    ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('product_price_status', $status))
    ->when($filters['area'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
    ->when($filters['category'] ?? null, fn ($q, $id) => $q->whereHas('product', fn ($p) => $p->where('category_id', $id)))
    ->orderByRaw("case product_price_status when 'pending' then 0 when 'flagged' then 1 else 2 end")
    ->orderByDesc('id')
    ->paginate(15)
    ->appends($filters);

$categories = Category::ordered()->get(['id', 'category_name_ar', 'category_name_en']);
$areas = Area::ordered()->get(['id', 'area_name_ar']);

return response()->view('cms.product_price.index', compact('prices', 'categories', 'areas', 'filters', 'ignoredFilters'));
}

public function show(string $id) {
$price = ProductPrice::with([
    'user',
    'product.category',
    'product.unit',
    'area.region',
    'votes' => fn ($q) => $q->orderBy('id'),
    'votes.user:id,user_full_name,user_phone',
])->findOrFail($id);

$confirmedBy = $price->votes->where('price_vote_type', PriceVote::TYPE_CONFIRM)->values();
$reportedBy = $price->votes->where('price_vote_type', PriceVote::TYPE_REPORT)->values();

return response()->view('cms.product_price.show', compact('price', 'confirmedBy', 'reportedBy'));
}

public function receipt(string $id) {
$price = ProductPrice::findOrFail($id);
$path = $price->product_price_receipt_image;

abort_unless($path && Storage::disk('local')->exists($path), 404);

return Storage::disk('local')->response($path);
}

public function approve(string $id) {
$price = ProductPrice::findOrFail($id);

$approved = DB::transaction(function () use ($price) {
    $row = ProductPrice::query()->whereKey($price->id)->lockForUpdate()->first();

    if (! $row || $row->product_price_status === ProductPrice::STATUS_APPROVED) {
        return false;
    }

if ($row->product_price_status === ProductPrice::STATUS_FLAGGED || $row->report_count > ProductPrice::MAX_REPORTS) {
    PriceVote::query()
        ->where('product_price_id', $row->id)
        ->where('price_vote_type', PriceVote::TYPE_REPORT)
        ->delete();

    $row->report_count = 0;
    }

    $row->product_price_status = ProductPrice::STATUS_APPROVED;
    $row->reviewed_at = now();
    $row->save();

    return true;
});

if (! $approved) {
    return response()->json(['icon' => 'error', 'title' => 'This price is already visible'], 409);
}

PriceTicker::forget();

return response()->json(['redirect' => route('product_price.show', $price->id)], 200);
}

public function reject(string $id) {
$price = ProductPrice::findOrFail($id);

$updated = ProductPrice::query()
    ->whereKey($price->id)
    ->whereIn('product_price_status', [
        ProductPrice::STATUS_PENDING,
        ProductPrice::STATUS_APPROVED,
        ProductPrice::STATUS_FLAGGED,
    ])
    ->update([
        'product_price_status' => ProductPrice::STATUS_REJECTED,
        'reviewed_at' => now(),
        'updated_at' => now(),
    ]);

if (! $updated) {
    return response()->json(['icon' => 'error', 'title' => 'This price is already hidden'], 409);
}

PriceTicker::forget();

return response()->json(['redirect' => route('product_price.show', $price->id)], 200);
}

public function destroy(string $id)
{
$price = ProductPrice::findOrFail($id);

$deleted = ProductPrice::whereKey($price->id)->delete();

if (! $deleted) {
    return response()->json(['icon' => 'error', 'title' => 'Price not found'], 404);
}

if ($price->product_price_receipt_image) {
    Storage::disk('local')->delete($price->product_price_receipt_image);
}

PriceTicker::forget();

return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
}
}