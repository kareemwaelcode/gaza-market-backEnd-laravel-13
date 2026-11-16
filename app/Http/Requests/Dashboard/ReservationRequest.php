<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardReservation;
use App\Models\DashboardTable;
use App\Models\Place;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/** طلبا القائمة (GET) وتغيير الحالة (PATCH). النشاط غير المدعوم يرجع 404 قبل التحقق. */
class ReservationRequest extends DashboardRequest
{
    public function authorize(): bool
    {
        $place = Place::where('user_id', $this->user()->id)->first();

        if ($place === null || ! DashboardTable::supportsPlace($place)) {
            throw new HttpResponseException(response()->json(['code' => 'NOT_FOUND'], 404));
        }

        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->query('q'))) {
            $this->query->set('q', trim($this->query('q')));
        }
    }

    public function rules(): array
    {
        if ($this->isMethod('PATCH')) {
            return [
                'status' => ['required', 'string', Rule::in([
                    DashboardReservation::STATUS_CONFIRMED,
                    DashboardReservation::STATUS_CANCELLED,
                ])],
            ];
        }

        return [
            'status' => ['nullable', 'string', Rule::in(DashboardReservation::STATUSES)],
            'q' => ['nullable', 'string', 'max:80'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('dashboard_plan.reservations.max_page_size')],
            'cursor' => ['nullable', 'string', 'max:500'],
        ];
    }
}