<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** أساس طلبات الدخول/التسجيل: يطبّع الرقم ويرجع أخطاء التحقق بنفس شكل بقية الـ API. */
abstract class AuthRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $merge = [];

        if (is_string($this->input('phone'))) {
            // أرقام عربية/هندية → لاتينية، وإزالة المسافات والشرطات.
            $phone = strtr($this->input('phone'), [
                '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
                '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            ]);
            $merge['phone'] = preg_replace('/[\s\-]+/', '', $phone);
        }

        if (is_string($this->input('code'))) {
            $merge['code'] = trim($this->input('code'));
        }

        if (is_string($this->input('full_name'))) {
            $merge['full_name'] = trim(preg_replace('/\s+/u', ' ', $this->input('full_name')) ?? '');
        }

        $this->merge($merge);
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}