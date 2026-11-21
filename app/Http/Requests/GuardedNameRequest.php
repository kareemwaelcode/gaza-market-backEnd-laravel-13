<?php

namespace App\Http\Requests\Cms;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;

/**
 * قواعد مشتركة للأدوار والصلاحيات (name + guard_name).
 *
 * الـ guards لازم تطابق مفاتيح config('auth.guards') لأن Spatie يستخدمها
 * لإيجاد موديل المستخدم (مثلاً عند حذف دور: users()->detach()).
 */
abstract class GuardedNameRequest extends FormRequest
{
    public const GUARDS = ['admins', 'users'];

    /** اسم الجدول الفعلي (من config/permission.php). */
    abstract protected function table(): string;

    /** اسم باراميتر الـ resource route (role / permission). */
    abstract protected function resourceParam(): string;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // مسار التحديث بيجي مرة بـ {id} (roles_update) ومرة بـ {role}/{permission} (resource).
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

    /**
     * نفس شكل الاستجابة القديم للـ CMS (JSON + 400) بدل 422 الافتراضي.
     */
    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'icon' => 'error',
            'title' => $validator->errors()->first(),
        ], 400));
    }
}