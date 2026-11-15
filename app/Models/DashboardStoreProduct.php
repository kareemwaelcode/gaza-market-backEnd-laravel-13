<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DashboardStoreProduct extends Model
{
public const EXTRA_KEYS = ['unit' => 40, 'origin' => 40, 'condition' => 40, 'expiry' => 20];
protected $table = 'dashboard_store_products';
protected $fillable = [
'name', 'price', 'is_available', 'has_discount', 'item_category_id',
'details', 'brand', 'size', 'color', 'material', 'extra',
];

protected function casts(): array
{
return [
    'place_id' => 'integer',
    'item_category_id' => 'integer',
    'price' => 'decimal:2',
    'is_available' => 'boolean',
    'has_discount' => 'boolean',
    'extra' => 'array',
];
}

protected function imageUrl(): Attribute
{
return Attribute::get(fn () => $this->image
    ? Storage::disk('public')->url($this->image)
    : null);
}

public function place(): BelongsTo
{
return $this->belongsTo(Place::class, 'place_id');
}

public function category(): BelongsTo
{
return $this->belongsTo(DashboardItemCategory::class, 'item_category_id');
}
}