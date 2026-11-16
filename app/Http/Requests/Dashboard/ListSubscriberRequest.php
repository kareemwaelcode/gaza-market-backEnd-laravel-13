<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardSubscriber;
use App\Models\Place;
use Illuminate\Http\Exceptions\HttpResponseException;

/** طلب قائمة المشتركين (GET). النشاط غير المدعوم يرجع 404 قبل التحقق. */
class ListSubscriberRequest extends DashboardRequest
{
    public function authorize(): bool
    {
        $place = Place::where('user_id', $this->user()->id)->first();

        if ($place === null || ! DashboardSubscriber::supportsPlace($place)) {
            throw new HttpResponseException(response()->json(['code' => 'NOT_FOUND'], 404));
        }

        return true;
    }

    public function rules(): array
    {
        return [
            'limit' => ['nullable', 'integer', 'min:1', 'max:'.(int) config('dashboard_plan.subscribers.max_page_size')],
            'cursor' => ['nullable', 'string', 'max:500'],
        ];
    }
}