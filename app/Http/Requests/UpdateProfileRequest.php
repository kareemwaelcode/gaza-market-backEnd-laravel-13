<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * تعديل بروفايل المستخدم الحالي. الحقول المسموحة فقط:
 * الدور والحالة والهاتف والنقاط ما بتتعدّل من هون أبداً (الهاتف هوية الدخول بالـ OTP).
 */
class UpdateProfileRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        return [
            'full_name' => ['sometimes', 'required', 'string', 'min:2', 'max:45'],
            'preferred_lang' => ['sometimes', 'required', Rule::in(['en', 'ar'])],
            'theme' => ['sometimes', 'required', Rule::in(['light', 'dark'])],
            'area_id' => ['sometimes', 'nullable', 'integer', Rule::exists('areas', 'id')],
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