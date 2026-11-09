<?php

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

class RequestOtpRequest extends AuthRequest
{
    public function rules(): array
    {
        return [
            'purpose' => ['required', 'string', Rule::in(config('otp.purposes'))],
            'phone' => ['required', 'string', 'regex:'.StorePlaceRequest::PHONE_REGEX],
            'full_name' => ['nullable', 'required_if:purpose,register', 'string', 'min:2', 'max:45'],
        ];
    }
}