<?php

namespace App\Http\Requests\Dashboard;

class UpdateStoreServiceRequest extends StoreStoreServiceRequest
{
    public function rules(): array
    {
        return $this->baseRules($this->placeId(), 'sometimes') + [
            'name' => ['sometimes', 'required', 'string', 'max:100'],
        ];
    }
}