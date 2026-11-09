<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class VerifyOtpRequest extends AuthRequest
{
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::in(config('otp.purposes'))],
            'phone' => ['required', 'string', 'regex:'.StorePlaceRequest::PHONE_REGEX],
            'code' => ['required', 'string', 'regex:/^\d{'.(int) config('otp.length').'}$/'],
        ];
    }
}