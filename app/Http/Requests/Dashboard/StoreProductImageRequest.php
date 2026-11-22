<?php

namespace App\Http\Requests\Dashboard;

class StoreProductImageRequest extends DashboardRequest
{
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }
}