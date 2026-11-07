<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * تحقق بيانات إنشاء إعلان.
 *
 * - كل الحقول إجبارية ما عدا listing_is_negotiable و listing_whatsapp.
 * - الصور: من 1 إلى 3.
 * - user_id و listing_status و listing_published_at ليست من المدخلات: صاحب الإعلان
 *   يؤخذ من المستخدم المسجّل، والحالة تبدأ pending ومخفية حتى موافقة الأدمن.
 */
class StoreListingRequest extends FormRequest
{
    // جوال فلسطيني: 10 أرقام، 059 أو 056 (مثال 0591234567)
    public const PHONE_REGEX = '/^05[69]\d{7}$/';

    // واتساب بصيغة دولية: + ثم رمز الدولة (3 أرقام) ثم الرقم بدون صفر بادئ، مثال +970591234567.
    // الرقم المحلي من 7 إلى 11 خانة، فالحد الأقصى 15 حرفاً وهو طول عمود listing_whatsapp.
    public const WHATSAPP_REGEX = '/^\+\d{3}[1-9]\d{6,10}$/';

    public function authorize(): bool
    {
        // الصلاحية تُضبط عند ربط الطلب بمسار مصادق عليه
        return true;
    }

    public function rules(): array
    {
        return [
            'listing_title' => ['required', 'string', 'max:100'],
            'listing_condition' => ['required', Rule::in(['new', 'used', 'urgent'])],
            'listing_description' => ['required', 'string', 'max:1000'],
            // أكبر من صفر، وبخانتين عشريتين كحد أقصى (decimal 10,2)
            'listing_price' => ['required', 'numeric', 'decimal:0,2', 'min:1', 'max:99999999.99'],
            // اختياري (يُرسل 1 أو 0)
            'listing_is_negotiable' => ['nullable', 'boolean'],
            'listing_phone' => ['required', 'string', 'regex:'.self::PHONE_REGEX],
            // اختياري
            'listing_whatsapp' => ['nullable', 'string', 'regex:'.self::WHATSAPP_REGEX],
            'listing_category_id' => [
                'required',
                'integer',
                Rule::exists('listing_categories', 'id')->where('listing_category_is_active', true),
            ],
            'area_id' => ['required', 'integer', Rule::exists('areas', 'id')],
            'images' => ['required', 'array', 'min:1', 'max:3'],
            'images.*' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
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