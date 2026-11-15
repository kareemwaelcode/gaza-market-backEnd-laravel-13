<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardItemCategory;
use App\Models\Place;
use Illuminate\Validation\Rule;

class StoreMenuItemRequest extends DashboardRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }

        if ($this->input('price') === '') {
            $this->merge(['price' => null]);
        }
    }

    public function rules(): array
    {
        $placeId = Place::where('user_id', $this->user()->id)->value('id');

        return [
            'name' => ['required', 'string', 'max:80'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:999999.99', 'decimal:0,2'],
            'is_available' => ['sometimes', 'boolean'],
            'item_category_id' => [
                'nullable',
                'integer',
                Rule::exists('dashboard_item_categories', 'id')->where(fn ($q) => $q
                    ->where('place_id', $placeId)
                    ->where('kind', DashboardItemCategory::KIND_MENU)),
            ],
        ];
    }
}