<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/** أساس طلبات الدخول/التسجيل: يطبّع الرقم ويرجع أخطاء التحقق بنفس شكل بقية الـ API. */
abstract class AuthRequest extends FormRequest
{
    /**
     * الصيغة الموحّدة للرقم بعد التطبيع: محلي 056/059 فقط (هوية الحساب)، سواء كُتب محلي أو 970 أو 972.
     */
    public const PHONE_REGEX = '/^05[69]\d{7}$/';

    /** وجهة الواتساب حسب الصيغة المكتوبة (+972...)، أو null لاستخدام الافتراضي (+970). */
    private ?string $otpDestination = null;

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
            $clean = (string) preg_replace('/[\s\-]+/', '', $phone);
            $this->otpDestination = self::destinationFor($clean);
            $merge['phone'] = self::canonicalPhone($clean);
        }

        if (is_string($this->input('code'))) {
            $merge['code'] = trim($this->input('code'));
        }

        if (is_string($this->input('full_name'))) {
            $merge['full_name'] = trim(preg_replace('/\s+/u', ' ', $this->input('full_name')) ?? '');
        }

        $this->merge($merge);
    }

    public function otpDestination(): ?string
    {
        return $this->otpDestination;
    }

    /**
     * يوحّد صيغ الرقم المقبولة قبل التحقق (هوية واحدة للحساب):
     * 0597379047 / +970597379047 / 00970597379047 / +972597379047 / 972597379047 → 0597379047
     * أي شيء آخر يبقى كما هو ليرفضه التحقق.
     */
    public static function canonicalPhone(string $phone): string
    {
        if (preg_match('/^(?:970|972)(5[69]\d{7})$/', self::internationalDigits($phone), $m)) {
            return '0'.$m[1];
        }

        return $phone;
    }

    /**
     * وجهة الإرسال: لو الرقم مكتوب بـ 972 يرجع +972...، وغير ذلك null فيستخدم الإرسال الافتراضي (+970).
     * الهوية (canonicalPhone) لا تتأثر، والوجهة لا تدخل بأي مفتاح cooldown أو حد.
     */
    public static function destinationFor(string $phone): ?string
    {
        if (preg_match('/^972(5[69]\d{7})$/', self::internationalDigits($phone), $m)) {
            return '+972'.$m[1];
        }

        return null;
    }

    private static function internationalDigits(string $phone): string
    {
        $digits = ltrim($phone, '+');

        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}