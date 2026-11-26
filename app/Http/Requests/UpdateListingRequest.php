<?php

namespace App\Http\Requests;

class UpdateListingRequest extends StoreListingRequest
{
    public function rules(): array
    {
        return array_merge(parent::rules(), [
            'images' => ['nullable', 'array', 'min:1', 'max:3'],
            // روابط الصور الحالية اللي بدّه يحتفظ فيها (نفس الروابط اللي رجّعها الـ API). الحد الفعلي بيتحقق منه الكنترولر
            'keep_images' => ['nullable', 'array', 'max:3'],
            'keep_images.*' => ['string', 'max:2000'],
        ]);
    }
}