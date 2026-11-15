<?php

namespace App\Http\Requests\Dashboard;

use App\Models\Place;

class UpdateStoreProductRequest extends StoreStoreProductRequest
{
public function rules(): array
{
$placeId = Place::where('user_id', $this->user()->id)->value('id');

return $this->baseRules($placeId, 'sometimes') + [
    'name' => ['sometimes', 'required', 'string', 'max:100'],
];
}
}