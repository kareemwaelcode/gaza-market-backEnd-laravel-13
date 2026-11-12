<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreProductRequest extends FormRequest
{
public function authorize(): bool
{
    return $this->user() !== null;
}

public function rules(): array
{
return [
    'product_name_ar' => ['nullable', 'string', 'max:100', 'required_without:product_name_en'],
    'product_name_en' => ['nullable', 'string', 'max:100', 'required_without:product_name_ar'],
    'category_id' => [
        'required',
        'integer',
        Rule::exists('categories', 'id')->where('category_is_active', true),
    ],
    'product_quantity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
    'unit_id' => [
        'required',
        'integer',
        Rule::exists('units', 'id')->where('unit_is_active', true),
    ],
    'product_price' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:99999999.99'],
    'product_store_name' => ['required', 'string', 'max:100'],
    'area_id' => ['required', 'integer', Rule::exists('areas', 'id')],
    'product_store_address' => ['nullable', 'string', 'max:150'],
    'product_store_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
    'product_receipt_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
];
}

// نفس شكل أخطاء الـ CMS الحالي: {icon, title} مع 400
protected function failedValidation(Validator $validator): void
{
    throw new HttpResponseException(response()->json([
        'icon' => 'error',
        'title' => $validator->getMessageBag()->first(),
    ], 400));
}
}