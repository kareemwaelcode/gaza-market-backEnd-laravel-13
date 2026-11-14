<?php

namespace App\Http\Requests;

use App\Models\Product;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class StoreProductPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
    return [
        // السعر لمنتج موجود ومعتمد بالكتالوج: ما بننشئ منتج جديد من هون
        'product_id' => [
            'required',
            'integer',
            Rule::exists('products', 'id')->where('product_status', Product::STATUS_ACTIVE),
        ],
        'region' => ['required', 'string', 'max:45', Rule::exists('areas', 'area_slug')],
        'price' => ['required', 'numeric', 'decimal:0,2', 'gt:0', 'max:99999999.99'],
        'store_name' => ['required', 'string', 'max:120'],
        'store_address' => ['nullable', 'string', 'max:255'],
        'store_phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{6,20}$/'],
        'receipt_image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
    ];
    }

    // نفس شكل أخطاء API الأسعار الحالي: {icon, title} مع 400
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'icon' => 'error',
            'title' => $validator->getMessageBag()->first(),
        ], 400));
    }
}