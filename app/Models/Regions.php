<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Regions extends Model
{
    protected $table = "regions";

    public $timestamps = false;
    protected $fillable = [
    "region_slug",
    "region_name_en",
    "region_name_ar",
    "region_sort_order",
];

    protected function casts(): array
    {
    return [
        "region_sort_order" => "integer",
    ];
    }

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class, "region_id")->orderBy("area_sort_order");
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy("region_sort_order")->orderBy("id");
    }
}