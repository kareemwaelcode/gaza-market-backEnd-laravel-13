<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardItemCategory;
use Illuminate\Validation\Rule;

class ListItemCategoryRequest extends DashboardRequest
{
    public function rules(): array
    {
        return [
            'kind' => ['required', 'string', Rule::in(DashboardItemCategory::KINDS)],
        ];
    }
}