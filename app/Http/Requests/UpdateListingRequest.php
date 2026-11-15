<?php

namespace App\Http\Requests;

class UpdateListingRequest extends StoreListingRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'images' => ['nullable', 'array', 'min:1', 'max:3'],
        ]);
    }
}