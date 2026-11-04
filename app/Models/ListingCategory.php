<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ListingCategory extends Model
{
    protected $table = 'listing_categories';

    protected $fillable = [
        'listing_category_slug',
        'listing_category_name_en',
        'listing_category_name_ar',
        'listing_category_is_active',
        'listing_category_sort_order',
    ];

    protected function casts(): array
    {
        return [
            'listing_category_is_active' => 'boolean',
            'listing_category_sort_order' => 'integer',
        ];
    }

    /* ---------------- Scopes ---------------- */

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('listing_category_sort_order')->orderBy('id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('listing_category_is_active', true);
    }
}