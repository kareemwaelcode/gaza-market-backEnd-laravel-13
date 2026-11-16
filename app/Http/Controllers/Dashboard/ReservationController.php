<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ReservationRequest;
use App\Models\DashboardReservation;
use App\Models\DashboardTable;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ReservationController extends Controller
{
    public function index(ReservationRequest $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        $query = DashboardReservation::where('place_id', $place->id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.addcslashes((string) $request->query('q'), '\\%_').'%');
        }

        $total = (clone $query)->count();

        $page = $query->with('table')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) ($request->query('limit') ?: config('dashboard_plan.reservations.page_size')));

        return response()->json([
            'data' => $page->getCollection()->map(fn (DashboardReservation $r) => $this->payload($r))->all(),
            'meta' => [
                'next_cursor' => $page->nextCursor()?->encode(),
                'has_more' => $page->hasMorePages(),
                'total' => $total,
            ],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $place = $this->ownPlaceOrNull($request);

        if ($place === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        $row = DashboardReservation::where('place_id', $place->id)
            ->selectRaw("COUNT(*) as total, SUM(status = 'pending') as pending, SUM(status = 'confirmed') as confirmed")
            ->first();

        return response()->json(['data' => [
            'pending' => (int) $row->pending,
            'confirmed' => (int) $row->confirmed,
            'total' => (int) $row->total,
            'limit' => $this->reservationLimit($place->place_plan),
            // الحذف الناعم لا يُنقص العدّاد: الحد على إجمالي ما استقبله النشاط.
            'used' => DashboardReservation::withTrashed()->where('place_id', $place->id)->count(),
            // الإخفاء بسبب انتهاء الباقة غير مبني بعد (تفعيل الباقة ب٨)، مثل الطاولات.
            'hidden' => 0,
        ]]);
    }

    public function update(ReservationRequest $request, string $reservation): JsonResponse
    {
        $place = $this->ownPlace($request);
        $target = (string) $request->validated('status');

        $result = DB::transaction(function () use ($place, $reservation, $target) {
            $found = DashboardReservation::where('place_id', $place->id)
                ->whereKey((int) $reservation)
                ->lockForUpdate()
                ->first();

            if ($found === null) {
                return $this->failure('NOT_FOUND', 404);
            }

            if (! $found->canMoveTo($target)) {
                return $this->failure('invalid_transition', 422);
            }

            if ($target === DashboardReservation::STATUS_CONFIRMED) {
                if ($found->dashboard_table_id !== null) {
                    // قفل الطاولة: أول تأكيد ينجح والثاني يرجع 409. المحذوفة ناعماً تُعامل كغير متاحة.
                    $table = DashboardTable::where('place_id', $place->id)
                        ->whereKey($found->dashboard_table_id)
                        ->lockForUpdate()
                        ->first();

                    if ($table === null || $table->status !== DashboardTable::STATUS_AVAILABLE) {
                        return $this->failure('table_unavailable', 409);
                    }

                    $table->status = DashboardTable::STATUS_OCCUPIED;
                    $table->save();
                }
            } elseif ($found->status === DashboardReservation::STATUS_CONFIRMED && $found->dashboard_table_id !== null) {
                // إلغاء حجز مؤكد: الطاولة ترجع متاحة إن كانت لسا موجودة.
                DashboardTable::where('place_id', $place->id)
                    ->whereKey($found->dashboard_table_id)
                    ->update(['status' => DashboardTable::STATUS_AVAILABLE]);
            }

            $found->status = $target;
            $found->save();

            return $found;
        });

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['data' => $this->payload($result->load('table'))]);
    }

    public function destroy(Request $request, string $reservation): JsonResponse|Response
    {
        $place = $this->ownPlaceOrNull($request);

        if ($place === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        $found = DashboardReservation::where('place_id', $place->id)->whereKey((int) $reservation)->first();

        if ($found === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        if ($found->status !== DashboardReservation::STATUS_CANCELLED) {
            return $this->failure('invalid_transition', 422);
        }

        $found->delete();

        return response()->noContent();
    }

    private function ownPlace(Request $request): Place
    {
        return Place::where('user_id', $request->user()->id)->firstOrFail();
    }

    /** null إذا النشاط لا يدعم الحجز (404). */
    private function ownPlaceOrNull(Request $request): ?Place
    {
        $place = $this->ownPlace($request);

        return DashboardTable::supportsPlace($place) ? $place : null;
    }

    /** null يعني بلا حد تجاري (الباقة المدفوعة). */
    private function reservationLimit(?string $plan): ?int
    {
        return $plan === 'paid'
            ? null
            : (int) config('dashboard_plan.reservations.free_limit');
    }

    /** @return array<string, mixed> المعرّفات نصوص والوقت UTC حسب عقد الفرونت. */
    private function payload(DashboardReservation $r): array
    {
        $table = $r->table;

        return [
            'id' => (string) $r->id,
            'name' => $r->name,
            'countryCode' => $r->country_code,
            'whatsapp' => $r->whatsapp,
            'datetime' => $r->reserved_at->toIso8601ZuluString('millisecond'),
            'peopleCount' => $r->people_count,
            'tableId' => $r->dashboard_table_id === null ? null : (string) $r->dashboard_table_id,
            'table' => $table === null ? null : ['id' => (string) $table->id, 'name' => $table->name],
            'tableDeleted' => $table !== null && $table->trashed(),
            'note' => $r->note ?? '',
            'status' => $r->status,
            'createdAt' => $r->created_at->toIso8601ZuluString('millisecond'),
        ];
    }

    /** @param  array<string, mixed>  $extra */
    private function failure(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['code' => $code], $extra), $status);
    }
}