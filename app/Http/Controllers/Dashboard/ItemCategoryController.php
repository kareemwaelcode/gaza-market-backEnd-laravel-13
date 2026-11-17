<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ListItemCategoryRequest;
use App\Http\Requests\Dashboard\StoreItemCategoryRequest;
use App\Http\Requests\Dashboard\UpdateItemCategoryRequest;
use App\Models\DashboardItemCategory;
use App\Models\Place;
use App\Support\PlanOverflow;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ItemCategoryController extends Controller
{
public function index(ListItemCategoryRequest $request): JsonResponse
{
    $place = $this->ownPlace($request);
    $kind = $request->validated('kind');

    $items = DashboardItemCategory::where('place_id', $place->id)
        ->where('kind', $kind)
        ->orderBy('id')
        ->limit((int) config('dashboard_plan.category_hard_cap'))
        ->get();

    $limit = $this->planLimit($place->place_plan, $kind);
    $kept = PlanOverflow::keptIds(
        DashboardItemCategory::where('place_id', $place->id)->where('kind', $kind),
        $limit,
    );
    $overCount = $items->filter(fn (DashboardItemCategory $c) => PlanOverflow::isOver($kept, $c->id))->count();

    return response()->json([
        'data' => $items->map(fn (DashboardItemCategory $c) => $this->payload($c, PlanOverflow::isOver($kept, $c->id)))->all(),
        'meta' => ['limit' => $limit, 'used' => $items->count(), 'overLimitCount' => $overCount],
    ]);
}

public function store(StoreItemCategoryRequest $request): JsonResponse
{
    $place = $this->ownPlace($request);
    $data = $request->validated();

    try {
        $category = DB::transaction(function () use ($place, $data) {
            $locked = Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

            $limit = $this->planLimit($locked->place_plan, $data['kind'])
                ?? (int) config('dashboard_plan.category_hard_cap');

            $used = DashboardItemCategory::where('place_id', $locked->id)
                ->where('kind', $data['kind'])
                ->count();

            if ($used >= $limit) {
                return null;
            }

            $category = new DashboardItemCategory(['kind' => $data['kind'], 'name' => $data['name']]);
            $category->place_id = $locked->id;
            $category->save();

            return $category;
        });
    } catch (UniqueConstraintViolationException) {
        return $this->failure('CATEGORY_EXISTS', 409);
    }

    if ($category === null) {
        return $this->failure('PLAN_LIMIT_REACHED', 403, [
            'limit' => $this->planLimit($place->place_plan, $data['kind']),
        ]);
    }

    return response()->json(['data' => $this->payload($category)], 201);
}

public function show(Request $request, string $category): JsonResponse
{
    $found = $this->ownCategory($request, $category);

    return $found === null
        ? $this->failure('NOT_FOUND', 404)
        : response()->json(['data' => $this->payloadFor($found)]);
}

public function update(UpdateItemCategoryRequest $request, string $category): JsonResponse
{
    $found = $this->ownCategory($request, $category);

    if ($found === null) {
        return $this->failure('NOT_FOUND', 404);
    }

    $found->name = $request->validated('name');

    try {
        $found->save();
    } catch (UniqueConstraintViolationException) {
        return $this->failure('CATEGORY_EXISTS', 409);
    }

    return response()->json(['data' => $this->payloadFor($found->refresh())]);
}

public function destroy(Request $request, string $category): JsonResponse|Response
{
    $found = $this->ownCategory($request, $category);

    if ($found === null) {
        return $this->failure('NOT_FOUND', 404);
    }

    $found->delete();

    return response()->noContent();
}

private function ownPlace(Request $request): Place
{
    return Place::where('user_id', $request->user()->id)->firstOrFail();
}

private function ownCategory(Request $request, string $id): ?DashboardItemCategory
{
    return DashboardItemCategory::where('place_id', $this->ownPlace($request)->id)
        ->whereKey((int) $id)
        ->first();
}

private function planLimit(?string $plan, string $kind): ?int
{
    if ($plan === 'paid') {
        return config('dashboard_plan.category_limits.paid');
    }

    $limit = config("dashboard_plan.category_limits.free.{$kind}");

    return $limit === null ? null : (int) $limit;
}

/** @return array<string, mixed> payload مع حساب علامة "فوق حد الباقة" لتصنيف واحد. */
private function payloadFor(DashboardItemCategory $category): array
{
    $kept = PlanOverflow::keptIds(
        DashboardItemCategory::where('place_id', $category->place_id)->where('kind', $category->kind),
        $this->planLimit($category->place->place_plan, $category->kind),
    );

    return $this->payload($category, PlanOverflow::isOver($kept, $category->id));
}

/** @return array<string, mixed> المعرّف نص حسب عقد الفرونت. */
private function payload(DashboardItemCategory $category, bool $overLimit = false): array
{
    return [
        'id' => (string) $category->id,
        'kind' => $category->kind,
        'name' => $category->name,
        'overLimit' => $overLimit,
        'createdAt' => $category->created_at?->toIso8601String(),
    ];
}

/** @param  array<string, mixed>  $extra */
private function failure(string $code, int $status, array $extra = []): JsonResponse
{
    return response()->json(array_merge(['code' => $code], $extra), $status);
}
}