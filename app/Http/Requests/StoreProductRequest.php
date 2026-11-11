<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

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
            'category_id' => ['required', 'integer', 'exists:categories,id'],
            'product_quantity' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
            'unit_id' => ['required', 'integer', 'exists:units,id'],
            'product_price' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:99999999.99'],
            'product_store_name' => ['required', 'string', 'max:100'],
            'area_id' => ['required', 'integer', 'exists:areas,id'],
            'product_store_address' => ['nullable', 'string', 'max:150'],
            'product_store_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
            'product_receipt_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }
}