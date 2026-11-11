<?php

namespace App\Models;

use App\Enums\ProductStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
    protected $fillable = [
        'product_name_ar',
        'product_name_en',
        'category_id',
        'product_quantity',
        'unit_id',
        'product_price',
        'product_store_name',
        'area_id',
        'product_store_address',
        'product_store_phone',
    ];

    protected $hidden = [
        'user_id',
        'product_store_phone',
        'product_receipt_image',
    ];

    protected $attributes = [
        'product_status' => 'pending',
    ];

    protected function casts(): array
    {
        return [
            'product_quantity' => 'decimal:2',
            'product_price' => 'decimal:2',
            'product_status' => ProductStatus::class,
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('product_status', ProductStatus::Active);
    }

    public function scopeSearch(Builder $query, string $term): Builder
    {
        $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

        return $query->where(fn (Builder $q) => $q
            ->whereRaw("product_name_ar like ? escape '!'", [$like])
            ->orWhereRaw("product_name_en like ? escape '!'", [$like]));
    }
}