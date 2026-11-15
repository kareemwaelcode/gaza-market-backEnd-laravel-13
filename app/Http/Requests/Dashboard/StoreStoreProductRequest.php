<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardItemCategory;
use App\Models\DashboardStoreProduct;
use App\Models\Place;
use Illuminate\Validation\Rule;
class StoreStoreProductRequest extends DashboardRequest
{
private const TRIMMED = ['name', 'details', 'brand', 'size', 'color', 'material'];

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
$placeId = Place::where('user_id', $this->user()->id)->value('id');

return $this->baseRules($placeId) + [
    'name' => ['required', 'string', 'max:100'],
];
}

/** @return array<string, mixed> */
protected function baseRules(?int $placeId, string $presence = ''): array
{
$optional = $presence === '' ? ['nullable'] : [$presence, 'nullable'];

$rules = [
    'price' => array_merge($optional, ['numeric', 'min:0', 'max:999999.99', 'decimal:0,2']),
    'is_available' => $presence === '' ? ['sometimes', 'boolean'] : [$presence, 'boolean'],
    'has_discount' => $presence === '' ? ['sometimes', 'boolean'] : [$presence, 'boolean'],
    'item_category_id' => array_merge($optional, [
        'integer',
        Rule::exists('dashboard_item_categories', 'id')->where(fn ($q) => $q
            ->where('place_id', $placeId)
            ->where('kind', DashboardItemCategory::KIND_PRODUCT)),
    ]),
    'details' => array_merge($optional, ['string', 'max:300']),
    'brand' => array_merge($optional, ['string', 'max:40']),
    'size' => array_merge($optional, ['string', 'max:20']),
    'color' => array_merge($optional, ['string', 'max:40']),
    'material' => array_merge($optional, ['string', 'max:40']),
    'extra' => array_merge($optional, ['array']),
];

foreach (DashboardStoreProduct::EXTRA_KEYS as $key => $max) {
    $rules["extra.{$key}"] = ['nullable', 'string', "max:{$max}"];
}

return $rules;
}
}