<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\MenuItemImageRequest;
use App\Http\Requests\Dashboard\StoreMenuItemRequest;
use App\Http\Requests\Dashboard\UpdateMenuItemRequest;
use App\Models\DashboardMenuItem;
use App\Models\Place;
use App\Support\PlanOverflow;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class MenuItemController extends Controller
{
private const MENU_PLACE_TYPES = ['restaurant', 'cafe', 'restaurant-cafe'];

public function index(Request $request): JsonResponse
{
$place = $this->ownPlace($request);

if (! $this->supportsMenu($place)) {
    return $this->failure('MENU_NOT_SUPPORTED', 403);
}

$items = DashboardMenuItem::where('place_id', $place->id)
    ->orderByDesc('id')
    ->limit((int) config('dashboard_plan.menu_item_hard_cap'))
    ->get();

$limit = $this->planLimit($place->place_plan);
$kept = PlanOverflow::keptIds(DashboardMenuItem::where('place_id', $place->id), $limit);
$overCount = $items->filter(fn (DashboardMenuItem $i) => PlanOverflow::isOver($kept, $i->id))->count();

return response()->json([
    'data' => $items->map(fn (DashboardMenuItem $i) => $this->payload($i, PlanOverflow::isOver($kept, $i->id)))->all(),
    'meta' => ['limit' => $limit, 'used' => $items->count(), 'overLimitCount' => $overCount],
]);
}

public function store(StoreMenuItemRequest $request): JsonResponse
{
$place = $this->ownPlace($request);

if (! $this->supportsMenu($place)) {
    return $this->failure('MENU_NOT_SUPPORTED', 403);
}

$data = $request->validated();

$item = DB::transaction(function () use ($place, $data) {
    $locked = Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

    $limit = $this->planLimit($locked->place_plan)
        ?? (int) config('dashboard_plan.menu_item_hard_cap');

    if (DashboardMenuItem::where('place_id', $locked->id)->count() >= $limit) {
        return null;
    }

    $item = new DashboardMenuItem($data);
    $item->place_id = $locked->id;
    $item->save();

    return $item;
});

if ($item === null) {
    return $this->failure('PLAN_LIMIT_REACHED', 403, ['limit' => $this->planLimit($place->place_plan)]);
}

return response()->json(['data' => $this->payload($item->refresh())], 201);
}

public function show(Request $request, string $item): JsonResponse
{
$found = $this->ownItem($request, $item);

return $found === null
    ? $this->failure('NOT_FOUND', 404)
    : response()->json(['data' => $this->payloadFor($found)]);
}

public function update(UpdateMenuItemRequest $request, string $item): JsonResponse
{
$found = $this->ownItem($request, $item);

if ($found === null) {
    return $this->failure('NOT_FOUND', 404);
}

$data = $request->validated();

if ($data === []) {
    return $this->failure('VALIDATION', 422, [
        'errors' => ['body' => ['No updatable fields were sent.']],
    ]);
}

$found->fill($data)->save();

return response()->json(['data' => $this->payloadFor($found->refresh())]);
}

public function destroy(Request $request, string $item): JsonResponse|Response
{
$found = $this->ownItem($request, $item);

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

public function image(MenuItemImageRequest $request, string $item): JsonResponse
{
$found = $this->ownItem($request, $item);

if ($found === null) {
    return $this->failure('NOT_FOUND', 404);
}

$old = $found->image;
$path = $request->file('image')->store("places/{$found->place_id}/menu", 'public');

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

return response()->json(['data' => $this->payloadFor($found->refresh())]);
}

public function destroyImage(Request $request, string $item): JsonResponse
{
$found = $this->ownItem($request, $item);

if ($found === null) {
    return $this->failure('NOT_FOUND', 404);
}

$old = $found->image;

if ($old !== null) {
    $found->image = null;
    $found->save();
    Storage::disk('public')->delete($old);
}

return response()->json(['data' => $this->payloadFor($found->refresh())]);
}

private function ownPlace(Request $request): Place
{
return Place::where('user_id', $request->user()->id)->firstOrFail();
}

private function supportsMenu(Place $place): bool
{
return in_array($place->place_type, self::MENU_PLACE_TYPES, true);
}

private function ownItem(Request $request, string $id): ?DashboardMenuItem
{
$place = $this->ownPlace($request);

if (! $this->supportsMenu($place)) {
    return null;
}

return DashboardMenuItem::where('place_id', $place->id)->whereKey((int) $id)->first();
}

private function planLimit(?string $plan): ?int
{
if ($plan === 'paid') {
    return config('dashboard_plan.item_limits.paid');
}

$limit = config('dashboard_plan.item_limits.free.menu');

return $limit === null ? null : (int) $limit;
}

/** @return array<string, mixed> payload مع حساب علامة "فوق حد الباقة" لصنف واحد. */
private function payloadFor(DashboardMenuItem $item): array
{
$place = $item->place;
$kept = PlanOverflow::keptIds(
    DashboardMenuItem::where('place_id', $item->place_id),
    $this->planLimit($place->place_plan),
);

return $this->payload($item, PlanOverflow::isOver($kept, $item->id));
}

/** @return array<string, mixed> المعرّفات نصوص حسب عقد الفرونت. */
private function payload(DashboardMenuItem $item, bool $overLimit = false): array
{
return [
    'id' => (string) $item->id,
    'name' => $item->name,
    'price' => $item->price === null ? null : (float) $item->price,
    'available' => (bool) $item->is_available,
    'categoryId' => $item->item_category_id === null ? null : (string) $item->item_category_id,
    'image' => $item->image_url,
    'overLimit' => $overLimit,
    'createdAt' => $item->created_at?->toIso8601String(),
];
}

/** @param  array<string, mixed>  $extra */
private function failure(string $code, int $status, array $extra = []): JsonResponse
{
return response()->json(array_merge(['code' => $code], $extra), $status);
}
}