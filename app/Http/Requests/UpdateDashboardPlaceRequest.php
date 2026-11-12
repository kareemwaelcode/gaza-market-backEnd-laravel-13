<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * تعديل بيانات المحل من لوحة صاحبه. القائمة أدناه هي الحقول الوحيدة المسموحة:
 * أي حقل آخر (status, plan, type, user_id, id ...) يُتجاهل ولا يصل للقاعدة.
 */
class UpdateDashboardPlaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:80'],
            'address' => ['sometimes', 'required', 'string', 'max:255'],
            'phone' => ['sometimes', 'required', 'string', 'regex:'.StorePlaceRequest::PHONE_REGEX],
            'whatsapp' => ['sometimes', 'required', 'string', 'regex:'.StorePlaceRequest::WHATSAPP_REGEX],
            'is_open' => ['sometimes', 'required', 'boolean'],
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