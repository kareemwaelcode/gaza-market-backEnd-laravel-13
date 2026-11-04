<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class NavigationItem extends Model
{
    protected $table = 'navigation_items';

    protected $fillable = [
        'navigation_item_slug',
        'navigation_item_name_ar',
        'navigation_item_name_en',
        'navigation_item_icon',
        'navigation_item_url',
        'navigation_item_sort_order',
        'navigation_item_is_active',
        'navigation_item_is_mobile',
        'navigation_item_is_desktop',
        'navigation_item_is_coming_soon',
    ];

    protected function casts(): array
    {
        return [
            'navigation_item_sort_order' => 'integer',
            'navigation_item_is_active' => 'boolean',
            'navigation_item_is_mobile' => 'boolean',
            'navigation_item_is_desktop' => 'boolean',
            'navigation_item_is_coming_soon' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('navigation_item_is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('navigation_item_sort_order')->orderBy('id');
    }
}