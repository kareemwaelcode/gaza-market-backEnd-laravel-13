<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardWorkspacePricing;
use App\Models\Place;
use Closure;
use Illuminate\Http\Exceptions\HttpResponseException;

class UpdateWorkspacePricingRequest extends DashboardRequest
{
    private const COLUMNS = [
        'priceHour' => 'price_hour',
        'priceHalfDay' => 'price_half_day',
        'priceFullDay' => 'price_full_day',
        'priceWeek' => 'price_week',
        'priceMonth' => 'price_month',
        'openTime' => 'open_time',
        'closeTime' => 'close_time',
        'seatsTotal' => 'seats_total',
        'seatsAvailable' => 'seats_available',
    ];

    public function authorize(): bool
    {
        $place = Place::where('user_id', $this->user()->id)->first();

        if ($place === null || ! DashboardWorkspacePricing::supportsPlace($place)) {
            throw new HttpResponseException(response()->json(['code' => 'NOT_FOUND'], 404));
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (array_keys(self::COLUMNS) as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $merge[$field] = $value === '' ? null : $value;
            }
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        $price = ['present', 'nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'];
        $time = ['present', 'nullable', 'date_format:H:i'];

        return [
            'priceHour' => $price,
            'priceHalfDay' => $price,
            'priceFullDay' => $price,
            'priceWeek' => $price,
            'priceMonth' => $price,
            'openTime' => $time,
            'closeTime' => $time,
            'seatsTotal' => ['present', 'nullable', 'integer', 'min:1', 'max:65535'],
            'seatsAvailable' => [
                'present',
                'nullable',
                'integer',
                'min:0',
                'max:65535',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $total = $this->input('seatsTotal');

                    if ($value !== null && is_numeric($total) && (int) $value > (int) $total) {
                        $fail('The seatsAvailable field must not be greater than seatsTotal.');
                    }
                },
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function pricingData(): array
    {
        $data = [];

        foreach ($this->validated() as $key => $value) {
            $data[self::COLUMNS[$key]] = $value;
        }

        return $data;
    }
}