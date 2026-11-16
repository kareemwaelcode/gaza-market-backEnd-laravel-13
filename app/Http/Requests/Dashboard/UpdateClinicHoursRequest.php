<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardAppointment;
use App\Models\DashboardClinicHour;
use App\Models\Place;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/** حفظ دوام العيادة كاملاً (7 أيام + مدة الموعد) مثل saveHours بالفرونت. */
class UpdateClinicHoursRequest extends DashboardRequest
{
    public function authorize(): bool
    {
        $place = Place::with('storeCategory:id,store_category_variant_key')
            ->where('user_id', $this->user()->id)
            ->first();

        if ($place === null || ! DashboardAppointment::supportsPlace($place)) {
            throw new HttpResponseException(response()->json(['code' => 'NOT_FOUND'], 404));
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'slot' => ['required', 'integer', Rule::in(config('dashboard_plan.appointments.slot_options'))],
            'days' => ['required', 'array', 'size:'.count(DashboardClinicHour::DAYS)],
            'days.*.key' => ['required', 'string', 'distinct', Rule::in(DashboardClinicHour::DAYS)],
            'days.*.open' => ['required', 'boolean'],
            'days.*.from' => ['nullable', 'required_if:days.*.open,true', 'date_format:H:i'],
            'days.*.to' => ['nullable', 'required_if:days.*.open,true', 'date_format:H:i'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            foreach ((array) $this->input('days', []) as $i => $day) {
                if (! is_array($day) || ! filter_var($day['open'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
                    continue;
                }

                $from = $day['from'] ?? null;
                $to = $day['to'] ?? null;

                // H:i نصان بنفس الطول فالمقارنة النصية صحيحة. الفرونت: وقت البداية قبل النهاية.
                if (is_string($from) && is_string($to) && $from >= $to) {
                    $v->errors()->add("days.$i.to", 'The closing time must be after the opening time.');
                }
            }
        });
    }
}