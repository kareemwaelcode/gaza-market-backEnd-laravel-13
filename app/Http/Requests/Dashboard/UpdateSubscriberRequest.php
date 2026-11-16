<?php

namespace App\Http\Requests\Dashboard;

/** تعديل جزئي: الحقول المرسلة فقط. الإيقاف والتجديد لهما مساران مستقلان. */
class UpdateSubscriberRequest extends StoreSubscriberRequest
{
    public function rules(): array
    {
        return $this->baseRules(partial: true);
    }
}