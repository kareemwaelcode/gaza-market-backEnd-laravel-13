<?php

namespace App\Http\Requests\Dashboard;

class MenuItemImageRequest extends DashboardRequest
{
    public function rules(): array
    {
        return [
            'image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }
}