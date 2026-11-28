<?php

namespace App\Support;

use App\Models\Product;
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

/**
 * الشكل اللي بيقراه الفرونت (home.js): name_ar / name_en / price.
 */
public static function publicItems(): array
{
return array_map(fn (array $item) => [
    'name_ar' => $item['product_name_ar'],
    'name_en' => $item['product_name_en'],
    'price' => $item['price'],
], self::items());
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
} catch (Throwable $e) {
    // الكاش وقع بين القراءة والقفل أو الكتابة: نفس سلوك ApiCache::remember، من الداتابيز بدل 500.
    report($e);

    return self::query();
}
}

private static function query(): array
{
// الشريط بيقرأ من جدول products بس: الاسم + product_price للمنتجات الفعّالة.
// المنتجات اللي بنفس الاسم بتظهر مرة وحدة بأرخص سعر (عند التعادل الأحدث).
$cheapest = [];

Product::query()
    ->active()
    ->select(['id', 'product_name_ar', 'product_name_en', 'product_price'])
    ->toBase()
    ->orderBy('id')
    ->cursor()
    ->each(function (object $row) use (&$cheapest) {
        $key = self::nameKey($row);
        $cents = (int) round(((float) $row->product_price) * 100);

        if (! isset($cheapest[$key]) || $cents <= $cheapest[$key]['cents']) {
            $cheapest[$key] = ['cents' => $cents, 'row' => $row];
        }
    });

return collect($cheapest)
    ->pluck('row')
    ->sortByDesc('id')
    ->take(max(1, (int) config('prices.ticker.limit')))
    ->map(fn (object $row) => [
        'product_id' => (int) $row->id,
        'product_name_ar' => $row->product_name_ar,
        'product_name_en' => $row->product_name_en,
        'price' => number_format((float) $row->product_price, 2, '.', ''),
    ])
    ->values()
    ->all();
}

private static function nameKey(object $row): string
{
$name = trim((string) ($row->product_name_ar ?: $row->product_name_en));

if ($name === '') {
    return 'product:'.$row->id;
}

return mb_strtolower(preg_replace('/\s+/u', ' ', $name));
}
}