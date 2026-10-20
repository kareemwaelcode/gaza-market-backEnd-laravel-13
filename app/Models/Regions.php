<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Query\Builder;
use Psy\Readline\Interactive\Renderer\Area;

class Regions extends Model
{
    protected $table = "regions";
    // الجدول بدون created_at / updated_at
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
 
    /* ---------------- Relationships ---------------- */
 
    // المحافظة الواحدة فيها مناطق كثيرة (areas.region_id)
    public function areas(): HasMany
    {
        return $this->hasMany(Area::class, "region_id")->orderBy("area_sort_order");
    }
 
    /* ---------------- Scopes ---------------- */
 
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy("region_sort_order")->orderBy("id");
    }
}
