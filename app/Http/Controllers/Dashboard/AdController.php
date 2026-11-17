<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\StoreAdRequest;
use App\Http\Requests\Dashboard\UpdateAdRequest;
use App\Models\DashboardAd;
use App\Models\Place;
use App\Support\PlanOverflow;
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

        $limit = $this->adLimit($place->place_plan);
        $kept = PlanOverflow::keptIds(DashboardAd::where('place_id', $place->id), $limit);
        $overCount = $ads->filter(fn (DashboardAd $a) => PlanOverflow::isOver($kept, $a->id))->count();

        return response()->json([
            'data' => $ads->map(fn (DashboardAd $a) => $this->payload($a, PlanOverflow::isOver($kept, $a->id)))->all(),
            'meta' => [
                'limit' => $limit,
                'overLimitCount' => $overCount,
                'used' => DashboardAd::where('place_id', $place->id)->count(),
            ],
        ]);
    }

    public function store(StoreAdRequest $request): JsonResponse
    {
        $place = $this->ownPlace($request);
        $data = $request->validated();

        if (! in_array($data['type'], DashboardAd::allowedTypes($place), true)) {
            return $this->typeFailure();
        }

        $data = $this->clean($place, $data);

        if ($failure = $this->priceFailureFor($place, $data['type'], $data['price_before'] ?? null, $data['price_after'] ?? null, true)) {
            return $failure;
        }

        $result = DB::transaction(function () use ($place, $data) {
            // قفل المحل يمنع طلبين متزامنين من تجاوز حد الباقة.
            $locked = Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

            $limit = $this->adLimit($locked->place_plan)
                ?? (int) config('dashboard_plan.ads.hard_cap');

            if (DashboardAd::where('place_id', $locked->id)->count() >= $limit) {
                return $this->failure('PLAN_LIMIT_REACHED', 403, [
                    'limit' => $this->adLimit($locked->place_plan),
                ]);
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
        $found = $this->ownAd($this->ownPlace($request), $ad);

        return $found === null
            ? $this->failure('NOT_FOUND', 404)
            : response()->json(['data' => $this->payloadFor($found)]);
    }

    public function update(UpdateAdRequest $request, string $ad): JsonResponse
    {
        $place = $this->ownPlace($request);
        $found = $this->ownAd($place, $ad);

        if ($found === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        $data = $request->validated();

        if ($data === []) {
            return $this->failure('VALIDATION', 422, [
                'errors' => ['body' => ['No updatable fields were sent.']],
            ]);
        }

        if (array_key_exists('type', $data) && ! in_array($data['type'], DashboardAd::allowedTypes($place), true)) {
            return $this->typeFailure();
        }

        // القيم الفعلية بعد التعديل (المرسل أو المخزّن).
        $type = $data['type'] ?? $found->type;
        $data = $this->clean($place, $data + ['type' => $type]);

        $before = array_key_exists('price_before', $data) ? $data['price_before'] : $found->price_before;
        $after = array_key_exists('price_after', $data) ? $data['price_after'] : $found->price_after;

        // تغيير النوع أو الأسعار يُفحص كاملاً؛ تعديل حقل آخر لا يرفض إعلاناً قديماً.
        $touchesPrices = array_key_exists('type', $request->validated())
            || array_key_exists('price_before', $data)
            || array_key_exists('price_after', $data);

        if ($failure = $this->priceFailureFor($place, $type, $before, $after, $touchesPrices)) {
            return $failure;
        }

        $found->fill($data)->save();

        return response()->json(['data' => $this->payloadFor($found->refresh())]);
    }

    public function destroy(Request $request, string $ad): JsonResponse|Response
    {
        $found = $this->ownAd($this->ownPlace($request), $ad);

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

    private function ownAd(Place $place, string $id): ?DashboardAd
    {
        return DashboardAd::where('place_id', $place->id)->whereKey((int) $id)->first();
    }

    /** null يعني بلا حد تجاري (الباقة المدفوعة). */
    private function adLimit(?string $plan): ?int
    {
        return $plan === 'paid'
            ? null
            : (int) config('dashboard_plan.ads.free_limit');
    }

    /**
     * يطابق الفرونت: الأسعار تُحفظ لإعلان عرض بمتجر خاص فقط، والرابط لإعلان وظيفة فقط
     * عند المطعم والمتجر الخاص؛ غير ذلك يُفرَّغ بدل أن يُرفض.
     *
     * @param  array<string, mixed>  $data  يحتوي type (المرسل أو المخزّن)
     * @return array<string, mixed>
     */
    private function clean(Place $place, array $data): array
    {
        if (! DashboardAd::allowsPrices($place, $data['type'])) {
            $data['price_before'] = null;
            $data['price_after'] = null;
        }

        if (! DashboardAd::allowsLink($place, $data['type'])) {
            $data['link'] = null;
        }

        return $data;
    }

    /**
     * عرض بمتجر خاص: السعران مطلوبان والسعر بعد أقل من قبل (الفرونت يرفض الحفظ غير ذلك).
     */
    private function priceFailureFor(Place $place, string $type, mixed $before, mixed $after, bool $requireBoth): ?JsonResponse
    {
        if (! DashboardAd::allowsPrices($place, $type)) {
            return null;
        }

        if ($requireBoth && ($before === null || $after === null)) {
            return $this->failure('VALIDATION', 422, [
                'errors' => [
                    ($before === null ? 'price_before' : 'price_after') => ['The offer prices are required.'],
                ],
            ]);
        }

        return $this->priceOrderInvalid($before, $after) ? $this->priceFailure() : null;
    }

    private function typeFailure(): JsonResponse
    {
        return $this->failure('VALIDATION', 422, [
            'errors' => ['type' => ['This ad type is not available for your place type.']],
        ]);
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

    /** @return array<string, mixed> payload مع حساب علامة "فوق حد الباقة" لإعلان واحد. */
    private function payloadFor(DashboardAd $a): array
    {
        $kept = PlanOverflow::keptIds(
            DashboardAd::where('place_id', $a->place_id),
            $this->adLimit($a->place->place_plan),
        );

        return $this->payload($a, PlanOverflow::isOver($kept, $a->id));
    }

    /** @return array<string, mixed> المعرّفات نصوص حسب عقد الفرونت. */
    private function payload(DashboardAd $a, bool $overLimit = false): array
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
            'overLimit' => $overLimit,
            'createdAt' => $a->created_at?->toIso8601String(),
        ];
    }

    /** @param  array<string, mixed>  $extra */
    private function failure(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['code' => $code], $extra), $status);
    }
}