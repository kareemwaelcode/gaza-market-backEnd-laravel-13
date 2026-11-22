<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Throwable;
class PriceTicker
{
public const KEY = 'prices:ticker:v2';
private const LOCK_KEY = 'prices:ticker:v2:lock';
private const LOCK_SECONDS = 15;

private const LOCK_WAIT_SECONDS = 5;

public static function items(): array
{
if (self::ttl() <= 0) {
    return self::query();
}

try {
    $cached = ApiCache::store()->get(self::KEY);
} catch (Throwable $e) {
    // الكاش واقع (Redis مثلاً): الزائر بياخد الشريط من الداتابيز بدل 500،
    // نفس سلوك ApiCache::remember.
    report($e);

    return self::query();
}

if (is_array($cached)) {
    return $cached;
}

return self::rebuildOnce();
}
public static function refresh(): array
{
$items = self::query();

if (self::ttl() > 0) {
    ApiCache::store()->put(self::KEY, $items, self::ttl());
}

return $items;
}

public static function refreshAfterChange(): void
{
try {
    self::refresh();
} catch (Throwable $e) {
    report($e);

    try {
        self::forget();
    } catch (Throwable) {
        
    }
}
}

public static function forget(): void
{
ApiCache::store()->forget(self::KEY);
}

private static function ttl(): int
{
return (int) config('prices.ticker.ttl');
}

private static function rebuildOnce(): array
{
try {
    return ApiCache::store()->lock(self::LOCK_KEY, self::LOCK_SECONDS)
        ->block(self::LOCK_WAIT_SECONDS, function () {
            $cached = ApiCache::store()->get(self::KEY);

            return is_array($cached) ? $cached : self::refresh();
        });
} catch (LockTimeoutException) {
    return self::query();
}
}

private static function query(): array
{
$latestIds = ProductPrice::query()
    ->approved()
    ->whereIn('product_id', Product::query()->active()->select('id'))
    ->selectRaw('max(id)')
    ->groupBy('product_id');

return ProductPrice::query()
    ->select(['id', 'product_id', 'product_price_value'])
    ->whereIn('id', $latestIds)
    ->with('product:id,product_name_ar,product_name_en')
    ->orderByDesc('id')
    ->limit(max(1, (int) config('prices.ticker.limit')))
    ->get()
    ->map(fn (ProductPrice $price) => [
        'product_id' => $price->product_id,
        'product_name_ar' => $price->product?->product_name_ar,
        'product_name_en' => $price->product?->product_name_en,
        'price' => $price->product_price_value,
    ])
    ->values()
    ->all();
}
}