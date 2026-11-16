<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitAppointmentRequest;
use App\Models\DashboardAppointment;
use App\Models\DashboardClinicHour;
use App\Models\Place;
use Carbon\CarbonInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** حجز موعد في عيادة معتمدة من الزبون (بلا تسجيل دخول، مع CSRF وthrottle). */
class AppointmentController extends Controller
{
    public function store(SubmitAppointmentRequest $request, string $slug): JsonResponse
    {
        $place = Place::approved()
            ->with('storeCategory:id,store_category_variant_key')
            ->where('place_slug', $slug)
            ->first();

        if ($place === null || ! DashboardAppointment::supportsPlace($place)) {
            return $this->failure('NOT_FOUND', 404);
        }

        $at = $request->scheduledAt();
        $schedule = DashboardClinicHour::scheduleFor($place->id);

        if (! $this->fitsSchedule($at, $schedule)) {
            return $this->failure('VALIDATION', 422, ['errors' => ['datetime' => ['book.errTime']]]);
        }

        $data = $request->appointmentData();

        $result = DB::transaction(function () use ($place, $data, $at, $schedule) {
            // قفل المحل يسلسل الطلبات المتزامنة: فحص الحد وفحص التعارض ثم الإنشاء كوحدة واحدة.
            $locked = Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

            $limit = DashboardAppointment::limitFor($locked->place_plan);

            if ($limit !== null && DashboardAppointment::usedBy($locked->id) >= $limit) {
                return $this->failure('plan_limit_reached', 403);
            }

            if (DashboardAppointment::confirmedOverlap($locked->id, $at, $schedule['slot'])) {
                return $this->failure('slot_unavailable', 409);
            }

            $appointment = new DashboardAppointment($data);
            $appointment->scheduled_at = $at;
            $appointment->place_id = $locked->id;
            $appointment->status = DashboardAppointment::STATUS_PENDING;
            $appointment->save();

            return $appointment;
        }, 3);

        if ($result instanceof JsonResponse) {
            return $result;
        }

        // خصوصية: الرد للزبون id وstatus فقط.
        return response()->json(['data' => [
            'id' => (string) $result->id,
            'status' => $result->status,
        ]], 201);
    }

    /**
     * الموعد ضمن يوم مفتوح، على شبكة المدة من وقت الفتح، وينتهي قبل الإغلاق (بتوقيت Asia/Gaza).
     *
     * @param  array{slot: int, days: array<string, array{open: bool, from: string, to: string}>}  $schedule
     */
    private function fitsSchedule(CarbonInterface $at, array $schedule): bool
    {
        $local = $at->copy()->setTimezone(config('dashboard_plan.appointments.timezone'));
        $day = $schedule['days'][DashboardClinicHour::dayKey($local->dayOfWeek)];

        if (! $day['open'] || $local->second !== 0) {
            return false;
        }

        $minutes = $local->hour * 60 + $local->minute;
        $open = $this->minutes($day['from']);
        $close = $this->minutes($day['to']);

        return $minutes >= $open
            && $minutes + $schedule['slot'] <= $close
            && ($minutes - $open) % $schedule['slot'] === 0;
    }

    private function minutes(string $hhmm): int
    {
        [$h, $m] = array_map('intval', explode(':', $hhmm));

        return $h * 60 + $m;
    }

    private function failure(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['code' => $code], $extra), $status);
    }
}