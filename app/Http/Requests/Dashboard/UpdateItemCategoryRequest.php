<?php

namespace App\Http\Requests\Dashboard;

/** التعديل للاسم فقط. نوع التصنيف ثابت بعد الإنشاء. */
class UpdateItemCategoryRequest extends DashboardRequest
{
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('name'))) {
            $this->merge(['name' => trim($this->input('name'))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:45'],
        ];
    }
}