<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StoreCategory extends Model
{
    protected $fillable = [
        "store_category_slug",
        "store_category_variant_key",
        "store_category_name_en",
        "store_category_name_ar",
        "store_category_is_active",
        "store_category_sort_order",
        "store_category_group_id",
    ];

    protected function casts(): array
    {
        return [
            "store_category_is_active" => "boolean",
            "store_category_sort_order" => "integer",
            "store_category_group_id" => "integer",
        ];
    }

    public function storeCategoryGroup(): BelongsTo
    {
        return $this->belongsTo(StoreCategoryGroup::class, "store_category_group_id");
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy("store_category_sort_order")->orderBy("id");
    }
}