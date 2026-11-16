<?php

namespace App\Http\Requests;

use App\Models\DashboardSubscriber;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SubmitSubscriptionRequestRequest extends FormRequest
{
    /** أبعد تاريخ بداية مسموح من اليوم. */
    public const MAX_DAYS_AHEAD = 90;

    private const COLUMNS = [
        'countryCode' => 'country_code',
        'startDate' => 'start_date',
    ];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (['name', 'whatsapp', 'note'] as $field) {
            if (is_string($this->input($field))) {
                $merge[$field] = trim($this->input($field));
            }
        }

        if (($merge['note'] ?? $this->input('note')) === '') {
            $merge['note'] = null;
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $today = Carbon::now(config('dashboard_plan.subscribers.timezone'));

        return [
            'name' => ['required', 'string', 'max:80'],
            'countryCode' => ['required', 'string', Rule::in(DashboardSubscriber::COUNTRY_CODES)],
            'whatsapp' => ['required', 'string', 'regex:/^5[0-9]{8}$/'],
            'duration' => ['required', 'string', Rule::in(DashboardSubscriber::DURATIONS)],
            'startDate' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$today->toDateString(),
                'before_or_equal:'.$today->copy()->addDays(self::MAX_DAYS_AHEAD)->toDateString(),
            ],
            'note' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<string, mixed> الحقول المتحقق منها بأسماء أعمدة الجدول. */
    public function requestData(): array
    {
        $data = [];

        foreach ($this->validated() as $key => $value) {
            $data[self::COLUMNS[$key] ?? $key] = $value;
        }

        return $data;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}