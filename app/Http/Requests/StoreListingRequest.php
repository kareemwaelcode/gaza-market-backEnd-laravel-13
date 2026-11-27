<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreListingRequest extends FormRequest
{
    public const PHONE_REGEX = '/^05[69]\d{7}$/';

    public const WHATSAPP_REGEX = '/^\+\d{3}[1-9]\d{6,10}$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'listing_title' => ['required', 'string', 'max:100'],
            'listing_condition' => ['required', Rule::in(['new', 'used', 'urgent'])],
            'listing_description' => ['required', 'string', 'max:1000'],
            'listing_price' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:99999999.99'],
            'listing_is_negotiable' => ['nullable', 'boolean'],
            'listing_phone' => ['required', 'string', 'regex:'.self::PHONE_REGEX],
            'listing_whatsapp' => ['nullable', 'string', 'regex:'.self::WHATSAPP_REGEX],
            'listing_category_id' => [
                'required',
                'integer',
                Rule::exists('listing_categories', 'id')->where('listing_category_is_active', true),
            ],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')],
            'images' => ['required', 'array', 'min:1', 'max:3'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'icon' => 'error',
            'title' => $validator->getMessageBag()->first(),
        ], 400));
    }
}