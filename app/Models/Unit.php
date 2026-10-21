<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Unit extends Model
{
    // الجدول بدون created_at / updated_at
    public $timestamps = false;
    protected $fillable = [
        "unit_slug",
        "unit_name_en",
        "unit_name_ar",
        "unit_is_active",
        "unit_sort_order",
    ];

    protected function casts(): array
    {
        return [
            "unit_is_active" => "boolean",
            "unit_sort_order" => "integer",
        ];
    }

    /* ---------------- Scopes ---------------- */

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy("unit_sort_order")->orderBy("id");
    }
}