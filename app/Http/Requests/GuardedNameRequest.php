<?php

namespace App\Http\Requests\Cms;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

abstract class GuardedNameRequest extends FormRequest
{
    public const GUARDS = ['admins', 'web'];

    abstract protected function table(): string;

    abstract protected function resourceParam(): string;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $currentId = $this->route('id') ?? $this->route($this->resourceParam());

        return [
            'guard_name' => ['required', Rule::in(self::GUARDS)],
            'name' => [
                'required',
                'string',
                'max:125',
                Rule::unique($this->table(), 'name')
                    ->where('guard_name', $this->input('guard_name'))
                    ->ignore($currentId),
            ],
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'icon' => 'error',
            'title' => $validator->errors()->first(),
        ], 400));
    }
}