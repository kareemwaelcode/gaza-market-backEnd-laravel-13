<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreAdRequest;
use App\Http\Requests\Dashboard\UpdateAdRequest;
use App\Models\DashboardAd;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AdController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        $ads = DashboardAd::where('place_id', $place->id)
            ->orderByDesc('id')
            ->limit((int) config('dashboard_plan.ads.hard_cap'))
            ->get();

        return response()->json([
            'data' => $ads->map(fn (DashboardAd $a) => $this->payload($a))->all(),
            'meta' => [
                'used' => DashboardAd::where('place_id', $place->id)->count(),
            ],
        ]);
    }

    public function store(StoreAdRequest $request): JsonResponse
    {
        $place = $this->ownPlace($request);
        $data = $request->validated();

        if ($this->priceOrderInvalid($data['price_before'] ?? null, $data['price_after'] ?? null)) {
            return $this->priceFailure();
        }

        $result = DB::transaction(function () use ($place, $data) {
            // قفل المحل يمنع طلبين متزامنين من تجاوز السقف التقني.
            $locked = Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

            if (DashboardAd::where('place_id', $locked->id)->count() >= (int) config('dashboard_plan.ads.hard_cap')) {
                return $this->failure('ADS_LIMIT_REACHED', 403);
            }

            $ad = new DashboardAd($data);
            $ad->place_id = $locked->id;
            $ad->save();

            return $ad;
        }, 3);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['data' => $this->payload($result->refresh())], 201);
    }

    public function show(Request $request, string $ad): JsonResponse
    {
        $found = $this->ownAd($request, $ad);

        return $found === null
            ? $this->failure('NOT_FOUND', 404)
            : response()->json(['data' => $this->payload($found)]);
    }

    public function update(UpdateAdRequest $request, string $ad): JsonResponse
    {
        $found = $this->ownAd($request, $ad);

        if ($found === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        $data = $request->validated();

        if ($data === []) {
            return $this->failure('VALIDATION', 422, [
                'errors' => ['body' => ['No updatable fields were sent.']],
            ]);
        }

        // المقارنة على القيم الفعلية بعد التعديل (المرسل أو المخزّن).
        $before = array_key_exists('price_before', $data) ? $data['price_before'] : $found->price_before;
        $after = array_key_exists('price_after', $data) ? $data['price_after'] : $found->price_after;

        if ($this->priceOrderInvalid($before, $after)) {
            return $this->priceFailure();
        }

        $found->fill($data)->save();

        return response()->json(['data' => $this->payload($found->refresh())]);
    }

    public function destroy(Request $request, string $ad): JsonResponse|Response
    {
        $found = $this->ownAd($request, $ad);

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

    private function ownAd(Request $request, string $id): ?DashboardAd
    {
        $place = $this->ownPlace($request);

        return DashboardAd::where('place_id', $place->id)->whereKey((int) $id)->first();
    }

    /** السعر بعد الخصم لازم يكون أقل من السعر قبله عند وجود الاثنين. */
    private function priceOrderInvalid(mixed $before, mixed $after): bool
    {
        return $before !== null && $after !== null && (float) $after >= (float) $before;
    }

    private function priceFailure(): JsonResponse
    {
        return $this->failure('VALIDATION', 422, [
            'errors' => ['price_after' => ['The price after must be less than the price before.']],
        ]);
    }

    /** @return array<string, mixed> المعرّفات نصوص حسب عقد الفرونت. */
    private function payload(DashboardAd $a): array
    {
        return [
            'id' => (string) $a->id,
            'type' => $a->type,
            'title' => $a->title,
            'details' => $a->details,
            'date' => $a->date?->format('Y-m-d'),
            'link' => $a->link,
            'priceBefore' => $a->price_before === null ? null : (float) $a->price_before,
            'priceAfter' => $a->price_after === null ? null : (float) $a->price_after,
            'isHidden' => (bool) $a->is_hidden,
            'createdAt' => $a->created_at?->toIso8601String(),
        ];
    }

    /** @param  array<string, mixed>  $extra */
    private function failure(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['code' => $code], $extra), $status);
    }
}