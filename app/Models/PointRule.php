<?php

namespace App\Models;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PointRule extends Model
{
protected $table = 'point_rules';

protected $fillable = [
'point_rule_key',
'point_rule_name_en',
'point_rule_name_ar',
'point_rule_points',
'point_rule_daily_limit',
'point_rule_is_active',
'point_rule_sort_order',
];

protected function casts(): array
{
return [
    'point_rule_points' => 'integer',
    'point_rule_daily_limit' => 'integer',
    'point_rule_is_active' => 'boolean',
    'point_rule_sort_order' => 'integer',
];
}

/* ---------------- Scopes ---------------- */

public function scopeOrdered(Builder $query): Builder
{
return $query->orderBy('point_rule_sort_order')->orderBy('id');
}

}
