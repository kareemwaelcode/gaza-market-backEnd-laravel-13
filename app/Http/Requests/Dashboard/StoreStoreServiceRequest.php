<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardItemCategory;
use App\Models\Place;
use Illuminate\Validation\Rule;

class StoreStoreServiceRequest extends DashboardRequest
{
    private const TRIMMED = ['name', 'description', 'duration'];

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (self::TRIMMED as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $merge[$field] = ($value === '' && $field !== 'name') ? null : $value;
            }
        }

        if ($this->input('price') === '') {
            $merge['price'] = null;
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return $this->baseRules($this->placeId()) + [
            'name' => ['required', 'string', 'max:100'],
        ];
    }

    protected function placeId(): ?int
    {
        return Place::where('user_id', $this->user()->id)->value('id');
    }

    /** @return array<string, mixed> */
    protected function baseRules(?int $placeId, string $presence = ''): array
    {
        $optional = $presence === '' ? ['nullable'] : [$presence, 'nullable'];

        return [
            'description' => array_merge($optional, ['string', 'max:300']),
            'price' => array_merge($optional, ['numeric', 'min:0', 'max:999999.99', 'decimal:0,2']),
            'price_from' => $presence === '' ? ['sometimes', 'boolean'] : [$presence, 'boolean'],
            'duration' => array_merge($optional, ['string', 'max:40']),
            'item_category_id' => array_merge($optional, [
                'integer',
                Rule::exists('dashboard_item_categories', 'id')->where(fn ($q) => $q
                    ->where('place_id', $placeId)
                    ->where('kind', DashboardItemCategory::KIND_SERVICE)),
            ]),
        ];
    }
}