<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreStoreServiceRequest;
use App\Http\Requests\Dashboard\UpdateStoreServiceRequest;
use App\Models\DashboardStoreService;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class StoreServiceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        if (! $this->supportsServices($place)) {
            return $this->failure('STORE_SERVICES_NOT_SUPPORTED', 403);
        }

        $items = DashboardStoreService::where('place_id', $place->id)
            ->orderByDesc('id')
            ->limit((int) config('dashboard_plan.service_items.hard_cap'))
            ->get();

        return response()->json([
            'data' => $items->map(fn (DashboardStoreService $s) => $this->payload($s))->all(),
            'meta' => [
                'limit' => $this->serviceLimit($place->place_plan),
                'used' => DashboardStoreService::where('place_id', $place->id)->count(),
            ],
        ]);
    }

    public function store(StoreStoreServiceRequest $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        if (! $this->supportsServices($place)) {
            return $this->failure('STORE_SERVICES_NOT_SUPPORTED', 403);
        }

        $data = $this->cleanData($request->validated(), null);

        $result = DB::transaction(function () use ($place, $data) {
            // قفل المحل يمنع طلبين متزامنين من تجاوز حد الباقة.
            $locked = Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

            $limit = $this->serviceLimit($locked->place_plan)
                ?? (int) config('dashboard_plan.service_items.hard_cap');

            if (DashboardStoreService::where('place_id', $locked->id)->count() >= $limit) {
                return $this->failure('PLAN_LIMIT_REACHED', 403, [
                    'limit' => $this->serviceLimit($locked->place_plan),
                ]);
            }

            $service = new DashboardStoreService($data);
            $service->place_id = $locked->id;
            $service->save();

            return $service;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['data' => $this->payload($result->refresh())], 201);
    }

    public function show(Request $request, string $service): JsonResponse
    {
        $found = $this->ownService($request, $service);

        return $found === null
            ? $this->failure('NOT_FOUND', 404)
            : response()->json(['data' => $this->payload($found)]);
    }

    public function update(UpdateStoreServiceRequest $request, string $service): JsonResponse
    {
        $found = $this->ownService($request, $service);

        if ($found === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        $data = $this->cleanData($request->validated(), $found);

        if ($data === []) {
            return $this->failure('VALIDATION', 422, [
                'errors' => ['body' => ['No updatable fields were sent.']],
            ]);
        }

        $found->fill($data)->save();

        return response()->json(['data' => $this->payload($found->refresh())]);
    }

    public function destroy(Request $request, string $service): JsonResponse|Response
    {
        $found = $this->ownService($request, $service);

        if ($found === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        $found->delete();

        return response()->noContent();
    }

    private function ownPlace(Request $request): Place
    {
        return Place::with('storeCategory:id,store_category_variant_key')
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    private function ownService(Request $request, string $id): ?DashboardStoreService
    {
        $place = $this->ownPlace($request);

        if (! $this->supportsServices($place)) {
            return null;
        }

        return DashboardStoreService::where('place_id', $place->id)->whereKey((int) $id)->first();
    }

    private function supportsServices(Place $place): bool
    {
        $variant = $place->storeCategory?->store_category_variant_key ?? 'general';

        return $place->place_type === 'store'
            && in_array($variant, config('dashboard_plan.service_items.variants'), true);
    }

    /** null يعني بلا حد تجاري (الباقة المدفوعة). */
    private function serviceLimit(?string $plan): ?int
    {
        return $plan === 'paid'
            ? null
            : (int) config('dashboard_plan.service_items.free_limit');
    }

    /**
     * "يبدأ من" لا معنى له بدون سعر، فيُلغى عند غياب السعر (المرسل أو المخزّن).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function cleanData(array $data, ?DashboardStoreService $current): array
    {
        if (array_key_exists('price_from', $data) || array_key_exists('price', $data)) {
            $price = array_key_exists('price', $data) ? $data['price'] : $current?->price;

            if ($price === null) {
                $data['price_from'] = false;
            }
        }

        return $data;
    }

    /** @return array<string, mixed> المعرّفات نصوص حسب عقد الفرونت. */
    private function payload(DashboardStoreService $s): array
    {
        return [
            'id' => (string) $s->id,
            'name' => $s->name,
            'description' => $s->description,
            'price' => $s->price === null ? null : (float) $s->price,
            'priceFrom' => (bool) $s->price_from,
            'duration' => $s->duration,
            'categoryId' => $s->item_category_id === null ? null : (string) $s->item_category_id,
            'createdAt' => $s->created_at?->toIso8601String(),
        ];
    }

    /** @param  array<string, mixed>  $extra */
    private function failure(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['code' => $code], $extra), $status);
    }
}