<?php

namespace App\Http\Requests\Dashboard;

use App\Models\DashboardAd;
use Illuminate\Validation\Rule;

class StoreAdRequest extends DashboardRequest
{
    private const TRIMMED = ['title', 'details', 'link'];

    protected function prepareForValidation(): void
    {
        $merge = [];

        foreach (self::TRIMMED as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $merge[$field] = ($value === '' && $field !== 'title') ? null : $value;
            }
        }

        foreach (['date', 'price_before', 'price_after'] as $field) {
            if ($this->input($field) === '') {
                $merge[$field] = null;
            }
        }

        $this->merge($merge);
    }

    public function rules(): array
    {
        return $this->baseRules() + [
            'type' => ['required', 'string', Rule::in(DashboardAd::TYPES)],
            'title' => ['required', 'string', 'max:100'],
        ];
    }

    /** @return array<string, mixed> */
    protected function baseRules(string $presence = ''): array
    {
        $optional = $presence === '' ? ['nullable'] : [$presence, 'nullable'];
        $price = ['numeric', 'min:0', 'max:999999.99', 'decimal:0,2'];

        return [
            'details' => array_merge($optional, ['string', 'max:300']),
            'date' => array_merge($optional, ['date_format:Y-m-d']),
            // http/https فقط: يمنع javascript: وغيره عندما يعرضه الفرونت كرابط.
            'link' => array_merge($optional, ['string', 'max:255', 'url:http,https']),
            'price_before' => array_merge($optional, $price),
            'price_after' => array_merge($optional, $price),
            'is_hidden' => $presence === '' ? ['sometimes', 'boolean'] : [$presence, 'boolean'],
        ];
    }
}