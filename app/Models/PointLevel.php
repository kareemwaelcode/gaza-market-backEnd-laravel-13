<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PointLevel extends Model
{
    protected $table = 'point_levels';

    protected $fillable = [
        'point_level_key',
        'point_level_name_en',
        'point_level_name_ar',
        'point_level_min_points',
        'point_level_sort_order',
    ];

    protected function casts(): array
    {
        return [
            'point_level_min_points' => 'integer',
            'point_level_sort_order' => 'integer',
        ];
    }

    /* ---------------- Scopes ---------------- */

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('point_level_sort_order')->orderBy('point_level_min_points');
    }

    /* ---------------- Helpers ---------------- */

    /**
     * Level for a given points total: the highest level whose min_points <= points.
     */
    public static function forPoints(int $points): ?self
    {
        return static::where('point_level_min_points', '<=', $points)
            ->orderByDesc('point_level_min_points')
            ->first();
    }
}