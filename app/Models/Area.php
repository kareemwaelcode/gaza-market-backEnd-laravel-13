<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Area extends Model 
{   
public $timestamps = false;
protected $fillable = [
    "area_slug",
    "area_name_en",
    "area_name_ar",
    "area_sort_order",
    "region_id",
];

protected function casts(): array
{
    return [
        "area_sort_order" => "integer",
        "region_id" => "integer",
    ];
}

    // المنطقة الواحدة تابعة لمحافظة واحدة (areas.region_id)
    public function region(): BelongsTo
    {
        return $this->belongsTo(Regions::class, "region_id");
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy("area_sort_order")->orderBy("id");
    }
}