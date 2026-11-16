<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ListSubscriptionRequestRequest;
use App\Models\DashboardSubscriber;
use App\Models\DashboardSubscriptionRequest;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class SubscriptionRequestController extends Controller
{
    public function index(ListSubscriptionRequestRequest $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        $base = DashboardSubscriptionRequest::where('place_id', $place->id);

        $query = (clone $base)->when(
            $request->query('status'),
            fn ($q, string $status) => $q->where('status', $status)
        );

        $total = (clone $query)->count();
        $pending = (clone $base)->where('status', DashboardSubscriptionRequest::STATUS_PENDING)->count();

        $page = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) ($request->query('limit') ?: config('dashboard_plan.subscribers.page_size')));

        return response()->json([
            'data' => $page->getCollection()->map(fn (DashboardSubscriptionRequest $r) => $this->payload($r))->all(),
            'meta' => [
                'next_cursor' => $page->nextCursor()?->encode(),
                'has_more' => $page->hasMorePages(),
                'total' => $total,
                'pending' => $pending,
            ],
        ]);
    }

    /** القبول ينشئ المشترك بمبلغ 0 ويغيّر الحالة بمعاملة واحدة؛ القفل يمنع قبولاً مزدوجاً. */
    public function accept(Request $request, string $subscriptionRequest): JsonResponse
    {
        [$found, $subscriber] = DB::transaction(function () use ($request, $subscriptionRequest) {
            $locked = $this->ownRequest($request, $subscriptionRequest, lock: true);

            if ($locked === null) {
                return [null, null];
            }

            if ($locked->status !== DashboardSubscriptionRequest::STATUS_PENDING) {
                return [$locked, null];
            }

            $subscriber = new DashboardSubscriber([
                'name' => $locked->name,
                'country_code' => $locked->country_code,
                'whatsapp' => $locked->whatsapp,
                'duration' => $locked->duration,
                'amount' => 0,
                'start_date' => $locked->start_date->toDateString(),
                'note' => $locked->note,
            ]);
            $subscriber->place_id = $locked->place_id;
            $subscriber->save();

            $locked->status = DashboardSubscriptionRequest::STATUS_ACCEPTED;
            $locked->save();

            return [$locked, $subscriber];
        });

        if ($found === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        if ($subscriber === null) {
            return $this->failure('ALREADY_HANDLED', 409);
        }

        return response()->json([
            'data' => $this->payload($found->refresh()),
            'subscriberId' => (string) $subscriber->id,
        ]);
    }

    public function reject(Request $request, string $subscriptionRequest): JsonResponse
    {
        $found = DB::transaction(function () use ($request, $subscriptionRequest) {
            $locked = $this->ownRequest($request, $subscriptionRequest, lock: true);

            if ($locked === null || $locked->status !== DashboardSubscriptionRequest::STATUS_PENDING) {
                return $locked;
            }

            $locked->status = DashboardSubscriptionRequest::STATUS_REJECTED;
            $locked->save();

            return $locked->setAttribute('was_pending', true);
        });

        if ($found === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        if (! $found->getAttribute('was_pending')) {
            return $this->failure('ALREADY_HANDLED', 409);
        }

        return response()->json(['data' => $this->payload($found->refresh())]);
    }

    public function destroy(Request $request, string $subscriptionRequest): JsonResponse|Response
    {
        $found = $this->ownRequest($request, $subscriptionRequest);

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

    /** الطلب يخص محل المالك فقط، والنشاط غير المدعوم يعامل كغير موجود. */
    private function ownRequest(Request $request, string $id, bool $lock = false): ?DashboardSubscriptionRequest
    {
        $place = $this->ownPlace($request);

        if (! DashboardSubscriber::supportsPlace($place)) {
            return null;
        }

        $query = DashboardSubscriptionRequest::where('place_id', $place->id)->whereKey((int) $id);

        if ($lock) {
            $query->lockForUpdate();
        }

        return $query->first();
    }

    /** @return array<string, mixed> المعرّفات نصوص حسب عقد الفرونت. */
    private function payload(DashboardSubscriptionRequest $r): array
    {
        return [
            'id' => (string) $r->id,
            'name' => $r->name,
            'countryCode' => $r->country_code,
            'whatsapp' => $r->whatsapp,
            'duration' => $r->duration,
            'startDate' => $r->start_date->toDateString(),
            'note' => $r->note ?? '',
            'status' => $r->status,
            'createdAt' => $r->created_at->toIso8601ZuluString('millisecond'),
        ];
    }

    private function failure(string $code, int $status): JsonResponse
    {
        return response()->json(['code' => $code], $status);
    }
}