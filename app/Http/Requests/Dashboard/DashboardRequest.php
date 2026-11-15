<?php

namespace App\Http\Requests\Dashboard;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

/**
 * أساس طلبات لوحة صاحب المحل: الصلاحية تتحقق من middleware (store.owner)،
 * وأخطاء التحقق ترجع بنفس شكل بقية لوحة المالك: { code: VALIDATION, errors }.
 */
abstract class DashboardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'code' => 'VALIDATION',
            'errors' => $validator->errors()->toArray(),
        ], 422));
    }
}