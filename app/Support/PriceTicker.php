<?php

namespace App\Support;

use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;

class PriceTicker
{
private const KEY = 'prices:ticker:v1';

public static function items(): array {
$ttl = (int) config('prices.ticker.ttl');

if ($ttl <= 0) {
    return self::query();
}

return Cache::remember(self::KEY, $ttl, fn () => self::query());
}

public static function forget(): void {
Cache::forget(self::KEY);
}

private static function query(): array {
return ProductPrice::query()
    ->approved()
    ->whereHas('product', fn (Builder $q) => $q->active())
    ->with([
        'product:id,product_name_ar,product_name_en,product_quantity,unit_id',
        'product.unit:id,unit_slug',
        'area:id,area_slug',
    ])
    ->orderByDesc('created_at')
    ->orderByDesc('id')
    ->limit(max(1, (int) config('prices.ticker.limit')))
    ->get()
    ->map(fn (ProductPrice $price) => [
        'price_id' => $price->id,
        'product_id' => $price->product_id,
        'product_name_ar' => $price->product?->product_name_ar,
        'product_name_en' => $price->product?->product_name_en,
        'quantity' => $price->product?->product_quantity,
        'unit' => $price->product?->unit?->unit_slug,
        'store_name' => $price->product_price_store_name,
        'region' => $price->area?->area_slug,
        'price' => $price->product_price_value,
        'created_at' => $price->created_at?->toJSON(),
    ])
    ->values()
    ->all();
}
}