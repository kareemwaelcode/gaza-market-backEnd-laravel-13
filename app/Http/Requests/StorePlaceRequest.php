<?php

namespace App\Http\Requests;

use App\Models\Place;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StorePlaceRequest extends FormRequest
{
    public const PHONE_REGEX = '/^05[69]\d{7}$/';

    public const WHATSAPP_REGEX = '/^(970|972)\d{8,9}$/';

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::in(Place::TYPES)],
            'sub_category' => [
                'nullable',
                'required_if:type,store',
                'string',
                'max:45',
                Rule::exists('store_categories', 'store_category_name_en')->where('store_category_is_active', true),
            ],
            'name' => ['required', 'string', 'max:80'],
            'region' => ['required', 'string', 'max:45', Rule::exists('areas', 'area_slug')],
            'address' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'regex:'.self::PHONE_REGEX],
            'whatsapp' => ['string', 'regex:'.self::WHATSAPP_REGEX],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}