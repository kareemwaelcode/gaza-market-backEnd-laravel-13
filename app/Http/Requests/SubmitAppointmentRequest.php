<?php

namespace App\Http\Requests;

use App\Models\DashboardAppointment;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

/** حجز موعد عيادة من الزبون (بلا تسجيل دخول). ساعات الدوام تُفحص بالـ controller لأنها تحتاج العيادة. */
class SubmitAppointmentRequest extends FormRequest
{
    private const ISO = '/^(\d{4})-(\d{2})-(\d{2})T(\d{2}):(\d{2})(?::(\d{2})(?:\.\d{1,6})?)?(Z|[+-]\d{2}:\d{2})$/';

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['name', 'whatsapp', 'reason', 'note'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field));
            }
        }

        foreach (['reason', 'note'] as $field) {
            if (($merge[$field] ?? $this->input($field)) === '') {
                $merge[$field] = null;
            }
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'countryCode' => ['required', 'string', Rule::in(DashboardAppointment::COUNTRY_CODES)],
            'whatsapp' => ['required', 'string', 'regex:/^5[0-9]{8}$/'],
            'datetime' => ['required', 'string', function (string $attribute, mixed $value, \Closure $fail): void {
                // شكل ISO وتاريخ/ساعة موجودان فعلاً (2026-02-31 و25:00 مرفوضان بدل أن يتحولا بصمت).
                if (! preg_match(self::ISO, (string) $value, $m)
                    || ! checkdate((int) $m[2], (int) $m[3], (int) $m[1])
                    || (int) $m[4] > 23 || (int) $m[5] > 59 || (int) ($m[6] ?? 0) > 59) {
                    $fail('book.errDate');

                    return;
                }

                try {
                    $at = Carbon::parse($value);
                } catch (\Throwable) {
                    $fail('book.errDate');

                    return;
                }

                if ($at->isPast()) {
                    $fail('book.errPast');
                } elseif ($at->gt(now()->addDays((int) config('dashboard_plan.appointments.window_days')))) {
                    $fail('book.errFar');
                }
            }],
            'reason' => ['nullable', 'string', 'max:100'],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** وقت الموعد بـ UTC (مُتحقق منه). */
    public function scheduledAt(): Carbon
    {
        return Carbon::parse($this->validated('datetime'))->utc();
    }

    /** @return array<string, mixed> الحقول بأسماء أعمدة الجدول (بلا datetime). */
    public function appointmentData(): array
    {
        $v = $this->validated();

        return [
            'name' => $v['name'],
            'country_code' => $v['countryCode'],
            'whatsapp' => $v['whatsapp'],
            'reason' => $v['reason'] ?? null,
            'note' => $v['note'] ?? null,
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}