<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Category extends Model
{
    protected $fillable = [
        "category_slug",
        "category_name_en",
        "category_name_ar",
        "category_is_active",
        "category_sort_order",
        "category_group_id",
    ];

    protected function casts(): array
    {
        return [
            "category_is_active" => "boolean",
            "category_sort_order" => "integer",
            "category_group_id" => "integer",
        ];
    }

    public function categoryGroup(): BelongsTo
    {
        return $this->belongsTo(CategoryGroup::class, "category_group_id");
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy("category_sort_order")->orderBy("id");
    }
}