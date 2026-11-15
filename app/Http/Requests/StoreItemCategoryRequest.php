<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardItemCategory;
use Illuminate\Validation\Rule;

/** الحقول المسموحة: kind و name فقط. أي حقل آخر (place_id, id ...) يُتجاهل. */
class StoreItemCategoryRequest extends DashboardRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(DashboardItemCategory::KINDS)],
            'name' => ['required', 'string', 'max:45'],
        ];
    }
}