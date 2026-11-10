<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** أساس طلبات الدخول/التسجيل: يطبّع الرقم ويرجع أخطاء التحقق بنفس شكل بقية الـ API. */
abstract class AuthRequest extends FormRequest
{
    /**
     * الصيغة الموحّدة للرقم بعد التطبيع: محلي 056/059 (يشمل أرقام 970)، أو دولي 972 بدون علامة +.
     */
    public const PHONE_REGEX = '/^(?:05[69]\d{7}|9725[69]\d{7})$/';

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
            $merge['phone'] = self::canonicalPhone((string) preg_replace('/[\s\-]+/', '', $phone));
        }

        if (is_string($this->input('code'))) {
            $merge['code'] = trim($this->input('code'));
        }

        if (is_string($this->input('full_name'))) {
            $merge['full_name'] = trim(preg_replace('/\s+/u', ' ', $this->input('full_name')) ?? '');
        }

        $this->merge($merge);
    }

    /**
     * يوحّد صيغ الرقم المقبولة قبل التحقق:
     * +970597379047 / 970597379047 / 00970597379047 → 0597379047 (نفس حساب الرقم المحلي)
     * +972592194533 / ++972592194533 / 00972592194533 → 972592194533
     * أي شيء آخر يبقى كما هو ليرفضه التحقق.
     */
    public static function canonicalPhone(string $phone): string
    {
        $digits = ltrim($phone, '+');

        if (str_starts_with($digits, '00')) {
            $digits = substr($digits, 2);
        }

        if (preg_match('/^970(5[69]\d{7})$/', $digits, $m)) {
            return '0'.$m[1];
        }

        if (preg_match('/^9725[69]\d{7}$/', $digits)) {
            return $digits;
        }

        return $phone;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}