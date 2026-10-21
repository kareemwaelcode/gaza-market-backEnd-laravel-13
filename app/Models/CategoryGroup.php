<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryGroup extends Model
{
    protected $fillable = [
        "category_group_slug",
        "category_group_name_en",
        "category_group_name_ar",
        "category_group_sort_order",
    ];

    protected function casts(): array
    {
        return [
            "category_group_sort_order" => "integer",
        ];
    }

    public function categories(): HasMany
    {
        return $this->hasMany(Category::class, "category_group_id")->orderBy("category_sort_order");
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy("category_group_sort_order")->orderBy("id");
    }
}