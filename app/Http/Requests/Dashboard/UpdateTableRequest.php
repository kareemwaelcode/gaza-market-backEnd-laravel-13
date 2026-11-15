<?php

namespace App\Http\Requests\Dashboard;

class UpdateTableRequest extends StoreTableRequest
{
    public function rules(): array
    {
        return $this->baseRules(partial: true) + [
            'name' => ['sometimes', 'required', 'string', 'max:40'],
        ];
    }
}