<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardTable;
use App\Models\Place;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreTableRequest extends DashboardRequest
{
    /** النشاط غير المدعوم يرجع 404 قبل التحقق من المدخلات. */
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
        $merge = [];

        if (is_string($this->input('name'))) {
            $merge['name'] = trim($this->input('name'));
        }

        if ($this->input('seats') === '') {
            $merge['seats'] = null;
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return $this->baseRules() + [
            'name' => ['required', 'string', 'max:40'],
        ];
    }

    /**
     * @param  bool  $partial  true في التعديل: الحقول المرسلة فقط (sometimes).
     * @return array<string, mixed>
     */
    protected function baseRules(bool $partial = false): array
    {
        return [
            'seats' => array_merge($partial ? ['sometimes'] : [], [
                'nullable', 'integer', 'min:1', 'max:'.(int) config('dashboard_plan.tables.max_seats'),
            ]),
            'status' => ['sometimes', Rule::in(DashboardTable::STATUSES)],
        ];
    }
}