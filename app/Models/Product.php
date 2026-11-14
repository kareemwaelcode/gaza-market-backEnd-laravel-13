<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

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

public function prices(): HasMany
{
return $this->hasMany(ProductPrice::class);
}

public function createFirstPrice(): ProductPrice
{
$copiedReceipt = null;

try {
    $price = new ProductPrice;
    $price->fill([
        'product_id' => $this->id,
        'area_id' => $this->area_id,
        'product_price_value' => $this->product_price,
        'product_price_store_name' => $this->product_store_name,
        'product_price_store_address' => $this->product_store_address,
        'product_price_store_phone' => $this->product_store_phone,
    ]);
    $price->user_id = $this->user_id;
    $price->product_price_status = ProductPrice::STATUS_APPROVED;
    $price->reviewed_at = now();

    $source = $this->product_receipt_image;

    if ($source && Storage::disk('local')->exists($source)) {
        $extension = pathinfo($source, PATHINFO_EXTENSION);
        $target = 'receipts/'.Str::random(40).($extension !== '' ? '.'.$extension : '');

        if (Storage::disk('local')->copy($source, $target)) {
            $copiedReceipt = $target;
            $price->product_price_receipt_image = $target;
        }
    }

    $price->save();

    return $price;
} catch (Throwable $e) {
    if ($copiedReceipt !== null) {
        Storage::disk('local')->delete($copiedReceipt);
    }

    throw $e;
}
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