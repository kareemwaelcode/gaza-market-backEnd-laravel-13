<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class DashboardMenuItem extends Model
{
protected $table = 'dashboard_menu_items';

protected $fillable = ['name', 'price', 'is_available', 'item_category_id'];

protected function casts(): array
{
return [
    'place_id' => 'integer',
    'item_category_id' => 'integer',
    'price' => 'decimal:2',
    'is_available' => 'boolean',
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