<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\AppointmentRequest;
use App\Models\DashboardAppointment;
use App\Models\DashboardClinicHour;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class AppointmentController extends Controller
{
    public function index(AppointmentRequest $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        $query = DashboardAppointment::where('place_id', $place->id);

        if ($request->filled('status')) {
            $query->where('status', $request->query('status'));
        }

        if ($request->filled('q')) {
            $query->where('name', 'like', '%'.addcslashes((string) $request->query('q'), '\\%_').'%');
        }

        $total = (clone $query)->count();

        $page = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->cursorPaginate((int) ($request->query('limit') ?: config('dashboard_plan.appointments.page_size')));

        return response()->json([
            'data' => $page->getCollection()->map(fn (DashboardAppointment $a) => $this->payload($a))->all(),
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

        $row = DashboardAppointment::where('place_id', $place->id)
            ->selectRaw("COUNT(*) as total, SUM(status = 'pending') as pending, SUM(status = 'confirmed') as confirmed")
            ->first();

        return response()->json(['data' => [
            'pending' => (int) $row->pending,
            'confirmed' => (int) $row->confirmed,
            'total' => (int) $row->total,
            'limit' => DashboardAppointment::limitFor($place->place_plan),
            // الحذف الناعم لا يُنقص العدّاد: الحد على إجمالي ما استقبلته العيادة.
            'used' => DashboardAppointment::usedBy($place->id),
        ]]);
    }

    public function update(AppointmentRequest $request, string $appointment): JsonResponse
    {
        $place = $this->ownPlace($request);
        $target = (string) $request->validated('status');

        $result = DB::transaction(function () use ($place, $appointment, $target) {
            // قفل المحل يسلسل التأكيدات: أول تأكيد ينجح والمتعارض معه يرجع 409.
            Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

            $found = DashboardAppointment::where('place_id', $place->id)
                ->whereKey((int) $appointment)
                ->lockForUpdate()
                ->first();

            if ($found === null) {
                return $this->failure('NOT_FOUND', 404);
            }

            if (! $found->canMoveTo($target)) {
                return $this->failure('invalid_transition', 422);
            }

            if ($target === DashboardAppointment::STATUS_CONFIRMED) {
                $slot = DashboardClinicHour::scheduleFor($place->id)['slot'];

                if (DashboardAppointment::confirmedOverlap($place->id, $found->scheduled_at, $slot, $found->id)) {
                    return $this->failure('slot_unavailable', 409);
                }
            }

            $found->status = $target;
            $found->save();

            return $found;
        }, 3);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        return response()->json(['data' => $this->payload($result)]);
    }

    public function destroy(Request $request, string $appointment): JsonResponse|Response
    {
        $place = $this->ownPlaceOrNull($request);

        if ($place === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        $found = DashboardAppointment::where('place_id', $place->id)->whereKey((int) $appointment)->first();

        if ($found === null) {
            return $this->failure('NOT_FOUND', 404);
        }

        if ($found->status !== DashboardAppointment::STATUS_CANCELLED) {
            return $this->failure('invalid_transition', 422);
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

    /** null إذا النشاط لا يدعم المواعيد (404). */
    private function ownPlaceOrNull(Request $request): ?Place
    {
        $place = $this->ownPlace($request);

        return DashboardAppointment::supportsPlace($place) ? $place : null;
    }

    /** @return array<string, mixed> المعرّفات نصوص والوقت UTC حسب عقد الفرونت. */
    private function payload(DashboardAppointment $a): array
    {
        return [
            'id' => (string) $a->id,
            'name' => $a->name,
            'countryCode' => $a->country_code,
            'whatsapp' => $a->whatsapp,
            'datetime' => $a->scheduled_at->toIso8601ZuluString('millisecond'),
            'reason' => $a->reason ?? '',
            'note' => $a->note ?? '',
            'status' => $a->status,
            'createdAt' => $a->created_at->toIso8601ZuluString('millisecond'),
        ];
    }

    /** @param  array<string, mixed>  $extra */
    private function failure(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['code' => $code], $extra), $status);
    }
}