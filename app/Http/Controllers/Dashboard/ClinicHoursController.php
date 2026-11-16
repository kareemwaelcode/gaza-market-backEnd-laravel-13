<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateClinicHoursRequest;
use App\Models\DashboardAppointment;
use App\Models\DashboardClinicHour;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/** دوام العيادة: 7 أيام + مدة الموعد، بنفس شكل getHours/saveHours بالفرونت. */
class ClinicHoursController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        if (! DashboardAppointment::supportsPlace($place)) {
            return response()->json(['code' => 'NOT_FOUND'], 404);
        }

        return response()->json(['data' => $this->payload($place->id)]);
    }

    public function update(UpdateClinicHoursRequest $request): JsonResponse
    {
        $place = $this->ownPlace($request);
        $defaults = config('dashboard_plan.appointments.default_hours');
        $slot = (int) $request->validated('slot');
        $now = now();

        $rows = [];

        foreach ($request->validated('days') as $day) {
            $rows[] = [
                'place_id' => $place->id,
                'day_of_week' => $day['key'],
                'is_open' => (bool) $day['open'],
                // يوم مغلق قد يصل بلا أوقات: نحفظ الافتراضي ليبقى العمودان غير فارغين.
                'open_time' => ($day['from'] ?? $defaults['from']).':00',
                'close_time' => ($day['to'] ?? $defaults['to']).':00',
                'slot_minutes' => $slot,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::transaction(function () use ($rows) {
            DashboardClinicHour::upsert(
                $rows,
                ['place_id', 'day_of_week'],
                ['is_open', 'open_time', 'close_time', 'slot_minutes', 'updated_at']
            );
        });

        return response()->json(['data' => $this->payload($place->id)]);
    }

    private function ownPlace(Request $request): Place
    {
        return Place::with('storeCategory:id,store_category_variant_key')
            ->where('user_id', $request->user()->id)
            ->firstOrFail();
    }

    /** @return array{slot: int, days: list<array{key: string, open: bool, from: string, to: string}>} */
    private function payload(int $placeId): array
    {
        $schedule = DashboardClinicHour::scheduleFor($placeId);

        return [
            'slot' => $schedule['slot'],
            'days' => array_map(
                fn (string $key) => ['key' => $key] + $schedule['days'][$key],
                DashboardClinicHour::DAYS
            ),
        ];
    }
}