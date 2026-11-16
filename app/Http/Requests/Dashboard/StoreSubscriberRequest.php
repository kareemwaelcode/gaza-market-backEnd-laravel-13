<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardSubscriber;
use App\Models\Place;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreSubscriberRequest extends DashboardRequest
{
    /** أسماء حقول الفرونت (camelCase) إلى أعمدة الجدول. */
    private const COLUMNS = [
        'countryCode' => 'country_code',
        'startDate' => 'start_date',
    ];

    /** النشاط غير المدعوم يرجع 404 قبل التحقق من المدخلات. */
    public function authorize(): bool
    {
        $place = Place::where('user_id', $this->user()->id)->first();

        if ($place === null || ! DashboardSubscriber::supportsPlace($place)) {
            throw new HttpResponseException(response()->json(['code' => 'NOT_FOUND'], 404));
        }

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

        if ($this->input('amount') === '') {
            $merge['amount'] = null;
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return $this->baseRules();
    }

    /**
     * @param  bool  $partial  true في التعديل: الحقول المرسلة فقط (sometimes).
     * @return array<string, mixed>
     */
    protected function baseRules(bool $partial = false): array
    {
        $sometimes = $partial ? ['sometimes'] : [];

        return [
            'name' => array_merge($sometimes, ['required', 'string', 'max:80']),
            'countryCode' => array_merge($sometimes, ['required', 'string', Rule::in(DashboardSubscriber::COUNTRY_CODES)]),
            // 9 خانات تبدأ بـ 5، نفس WHATSAPP_NATIONAL_RE بالفرونت.
            'whatsapp' => array_merge($sometimes, ['required', 'string', 'regex:/^5[0-9]{8}$/']),
            'duration' => array_merge($sometimes, ['required', 'string', Rule::in(DashboardSubscriber::DURATIONS)]),
            // null يعني 0 (الفرونت يرسل 0 عند ترك الحقل فارغاً). السقف = حد DECIMAL(8,2).
            'amount' => array_merge($sometimes, ['nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2']),
            'startDate' => array_merge($sometimes, ['required', 'date_format:Y-m-d']),
            'note' => array_merge($sometimes, ['nullable', 'string', 'max:300']),
        ];
    }

    /**
     * الحقول المتحقق منها بأسماء أعمدة الجدول، جاهزة للـ fill.
     *
     * @return array<string, mixed>
     */
    public function subscriberData(): array
    {
        $data = [];

        foreach ($this->validated() as $key => $value) {
            $data[self::COLUMNS[$key] ?? $key] = $value;
        }

        if (array_key_exists('amount', $data) && $data['amount'] === null) {
            $data['amount'] = 0;
        }

        return $data;
    }
}