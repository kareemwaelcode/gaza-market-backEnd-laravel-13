<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'product_name_ar' => $this->product_name_ar,
            'product_name_en' => $this->product_name_en,
            'product_quantity' => (float) $this->product_quantity,
            'product_price' => (float) $this->product_price,
            'product_store_name' => $this->product_store_name,
            'product_store_address' => $this->product_store_address,
            'category' => $this->whenLoaded('category', fn () => $this->category->only(['id', 'name'])),
            'unit' => $this->whenLoaded('unit', fn () => $this->unit->only(['id', 'name'])),
            'area' => $this->whenLoaded('area', fn () => $this->area->only(['id', 'name'])),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}