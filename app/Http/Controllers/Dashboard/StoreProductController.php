<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreProductImageRequest;
use App\Http\Requests\Dashboard\StoreStoreProductRequest;
use App\Http\Requests\Dashboard\UpdateStoreProductRequest;
use App\Models\DashboardStoreProduct;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;
class StoreProductController extends Controller
{
public function index(Request $request): JsonResponse
{
$place = $this->ownPlace($request);

if (! $this->supportsProducts($place)) {
    return $this->failure('STORE_PRODUCTS_NOT_SUPPORTED', 403);
}

$items = DashboardStoreProduct::where('place_id', $place->id)
    ->orderByDesc('id')
    ->limit((int) config('dashboard_plan.store_products.hard_cap'))
    ->get();

$variant = $this->variantOf($place);

return response()->json([
    'data' => $items->map(fn (DashboardStoreProduct $p) => $this->payload($p))->all(),
    'meta' => [
        'limit' => $this->productLimit($variant, $place->place_plan),
        'used' => DashboardStoreProduct::where('place_id', $place->id)->count(),
        'discountLimit' => $this->discountLimit($variant, $place->place_plan),
        'discountUsed' => DashboardStoreProduct::where('place_id', $place->id)->where('has_discount', true)->count(),
        'supportsDiscount' => $this->supportsDiscount($variant),
    ],
]);
}

public function store(StoreStoreProductRequest $request): JsonResponse
{
$place = $this->ownPlace($request);

if (! $this->supportsProducts($place)) {
    return $this->failure('STORE_PRODUCTS_NOT_SUPPORTED', 403);
}

$data = $this->cleanData($request->validated());
$variant = $this->variantOf($place);

if (($data['has_discount'] ?? false) && ! $this->supportsDiscount($variant)) {
    return $this->failure('DISCOUNT_NOT_SUPPORTED', 403);
}

$result = DB::transaction(function () use ($place, $variant, $data) {
    $locked = Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

    $limit = $this->productLimit($variant, $locked->place_plan)
        ?? (int) config('dashboard_plan.store_products.hard_cap');

    if (DashboardStoreProduct::where('place_id', $locked->id)->count() >= $limit) {
        return $this->failure('PLAN_LIMIT_REACHED', 403, [
            'limit' => $this->productLimit($variant, $locked->place_plan),
        ]);
    }

    if (($data['has_discount'] ?? false) && ($error = $this->discountLimitError($locked, $variant, null))) {
        return $error;
    }

    $product = new DashboardStoreProduct($data);
    $product->place_id = $locked->id;
    $product->save();

    return $product;
});

if ($result instanceof JsonResponse) {
    return $result;
}

return response()->json(['data' => $this->payload($result->refresh())], 201);
}

public function show(Request $request, string $product): JsonResponse
{
$found = $this->ownProduct($request, $product);

return $found === null
    ? $this->failure('NOT_FOUND', 404)
    : response()->json(['data' => $this->payload($found)]);
}

public function update(UpdateStoreProductRequest $request, string $product): JsonResponse
{
$place = $this->ownPlace($request);
$found = $this->ownProduct($request, $product);

if ($found === null) {
    return $this->failure('NOT_FOUND', 404);
}

$data = $this->cleanData($request->validated());

if ($data === []) {
    return $this->failure('VALIDATION', 422, [
        'errors' => ['body' => ['No updatable fields were sent.']],
    ]);
}

$variant = $this->variantOf($place);
$turnsDiscountOn = ($data['has_discount'] ?? false) && ! $found->has_discount;

if ($turnsDiscountOn && ! $this->supportsDiscount($variant)) {
    return $this->failure('DISCOUNT_NOT_SUPPORTED', 403);
}

$error = DB::transaction(function () use ($place, $variant, $found, $data, $turnsDiscountOn) {
    $locked = Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

    if ($turnsDiscountOn && ($error = $this->discountLimitError($locked, $variant, $found->id))) {
        return $error;
    }

    $found->fill($data)->save();

    return null;
});

if ($error !== null) {
    return $error;
}

return response()->json(['data' => $this->payload($found->refresh())]);
}

public function destroy(Request $request, string $product): JsonResponse|Response
{
$found = $this->ownProduct($request, $product);

if ($found === null) {
    return $this->failure('NOT_FOUND', 404);
}

$image = $found->image;
$found->delete();

if ($image !== null) {
    Storage::disk('public')->delete($image);
}

return response()->noContent();
}

public function image(StoreProductImageRequest $request, string $product): JsonResponse
{
$found = $this->ownProduct($request, $product);

if ($found === null) {
    return $this->failure('NOT_FOUND', 404);
}

$old = $found->image;
$path = $request->file('image')->store("places/{$found->place_id}/products", 'public');

if ($path === false) {
    return $this->failure('SERVER', 500);
}

try {
    $found->image = $path;
    $found->save();
} catch (Throwable $e) {
    Storage::disk('public')->delete($path);
    report($e);

    return $this->failure('SERVER', 500);
}

if ($old !== null && $old !== $path) {
    Storage::disk('public')->delete($old);
}

return response()->json(['data' => $this->payload($found->refresh())]);
}

public function destroyImage(Request $request, string $product): JsonResponse
{
$found = $this->ownProduct($request, $product);

if ($found === null) {
    return $this->failure('NOT_FOUND', 404);
}

$old = $found->image;

if ($old !== null) {
    $found->image = null;
    $found->save();
    Storage::disk('public')->delete($old);
}

return response()->json(['data' => $this->payload($found->refresh())]);
}

private function ownPlace(Request $request): Place
{
return Place::with('storeCategory:id,store_category_variant_key')
    ->where('user_id', $request->user()->id)
    ->firstOrFail();
}

private function ownProduct(Request $request, string $id): ?DashboardStoreProduct
{
$place = $this->ownPlace($request);

if (! $this->supportsProducts($place)) {
    return null;
}

return DashboardStoreProduct::where('place_id', $place->id)->whereKey((int) $id)->first();
}
private function variantOf(Place $place): string
{
return $place->storeCategory?->store_category_variant_key ?? 'general';
}

private function supportsProducts(Place $place): bool
{
return $place->place_type === 'store'
    && ! in_array($this->variantOf($place), config('dashboard_plan.store_products.no_product_variants'), true);
}

private function supportsDiscount(string $variant): bool
{
return in_array($variant, config('dashboard_plan.store_products.discount_variants'), true);
}

private function productLimit(string $variant, ?string $plan): ?int
{
if ($plan === 'paid') {
    return null;
}

return (int) (config("dashboard_plan.store_products.free_by_variant.{$variant}")
    ?? config('dashboard_plan.store_products.free_default'));
}

private function discountLimit(string $variant, ?string $plan): ?int
{
if ($plan === 'paid' || ! $this->supportsDiscount($variant)) {
    return null;
}

return (int) config('dashboard_plan.store_products.discounted_free');
}

private function discountLimitError(Place $locked, string $variant, ?int $excludeId): ?JsonResponse
{
$limit = $this->discountLimit($variant, $locked->place_plan);

if ($limit === null) {
    return null;
}

$used = DashboardStoreProduct::where('place_id', $locked->id)
    ->where('has_discount', true)
    ->when($excludeId !== null, fn ($q) => $q->whereKeyNot($excludeId))
    ->count();

return $used >= $limit
    ? $this->failure('DISCOUNT_LIMIT_REACHED', 403, ['limit' => $limit])
    : null;
}

/**
* extra: قيم فارغة تُحذف، وإن لم يبق شيء تصبح null.
*
* @param  array<string, mixed>  $data
* @return array<string, mixed>
*/
private function cleanData(array $data): array
{
if (array_key_exists('extra', $data)) {
    $extra = array_filter((array) $data['extra'], fn ($v) => $v !== null && $v !== '');
    $data['extra'] = $extra === [] ? null : $extra;
}

return $data;
}

/** @return array<string, mixed> المعرّفات نصوص حسب عقد الفرونت. */
private function payload(DashboardStoreProduct $p): array
{
return [
    'id' => (string) $p->id,
    'name' => $p->name,
    'price' => $p->price === null ? null : (float) $p->price,
    'available' => (bool) $p->is_available,
    'discount' => (bool) $p->has_discount,
    'categoryId' => $p->item_category_id === null ? null : (string) $p->item_category_id,
    'details' => $p->details,
    'brand' => $p->brand,
    'size' => $p->size,
    'color' => $p->color,
    'material' => $p->material,
    'extra' => $p->extra,
    'image' => $p->image_url,
    'createdAt' => $p->created_at?->toIso8601String(),
];
}

/** @param  array<string, mixed>  $extra */
private function failure(string $code, int $status, array $extra = []): JsonResponse
{
return response()->json(array_merge(['code' => $code], $extra), $status);
}
}