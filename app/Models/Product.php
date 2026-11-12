<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Product extends Model
{
public const STATUS_PENDING = 'pending';

public const STATUS_ACTIVE = 'active';

public const STATUS_HIDDEN = 'hidden';

public const STATUSES = [
    self::STATUS_PENDING,
    self::STATUS_ACTIVE,
    self::STATUS_HIDDEN,
];

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
    'product_status' => self::STATUS_PENDING,
];

protected function casts(): array
{
    return [
        'product_quantity' => 'decimal:2',
        'product_price' => 'decimal:2',
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
    return $this->belongsTo(User::class)->withTrashed();
}

public function scopeActive(Builder $query): Builder
{
    return $query->where('product_status', self::STATUS_ACTIVE);
}

public function scopeSearch(Builder $query, string $term): Builder
{
    $like = '%'.str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $term).'%';

    return $query->where(fn (Builder $q) => $q
        ->whereRaw("product_name_ar like ? escape '!'", [$like])
        ->orWhereRaw("product_name_en like ? escape '!'", [$like]));
}
}