<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardAd;
use Illuminate\Validation\Rule;

class UpdateAdRequest extends StoreAdRequest
{
    public function rules(): array
    {
        return $this->baseRules('sometimes') + [
            'type' => ['sometimes', 'required', 'string', Rule::in(DashboardAd::TYPES)],
            'title' => ['sometimes', 'required', 'string', 'max:100'],
        ];
    }
}