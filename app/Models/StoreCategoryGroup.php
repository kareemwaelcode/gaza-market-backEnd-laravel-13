<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StoreCategoryGroup extends Model
{
    protected $fillable = [
        "store_category_group_slug",
        "store_category_group_name_en",
        "store_category_group_name_ar",
        "store_category_group_sort_order",
    ];

    protected function casts(): array
    {
        return [
            "store_category_group_sort_order" => "integer",
        ];
    }

    public function storeCategories(): HasMany
    {
        return $this->hasMany(StoreCategory::class, "store_category_group_id")->orderBy("store_category_sort_order");
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy("store_category_group_sort_order")->orderBy("id");
    }
}