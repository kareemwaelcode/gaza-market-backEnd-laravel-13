<?php

namespace App\Http\Requests;

use App\Models\PriceVote;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

class VotePriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    public function rules(): array
    {
        // null = إلغاء الصوت (الفرونت بيبعت null لما المستخدم يضغط نفس الزر مرة ثانية)
        return [
            'vote' => ['present', 'nullable', Rule::in(PriceVote::TYPES)],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'icon' => 'error',
            'title' => $validator->getMessageBag()->first(),
        ], 400));
    }
}