<?php

namespace App\Support;

use Closure;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;
class ApiCache
{
public const PRICES = 'prices';
public const PRODUCTS = 'products';
public const MARKET = 'market';
public const PLACES = 'places';
public const LOOKUPS = 'lookups';
private const LOCK_SECONDS = 15;
private const LOCK_WAIT_SECONDS = 5;
private const VERSION_TTL_DAYS = 30;
public static function remember(array $groups, string $key, int $ttl, Closure $callback, ?Closure $isEmpty = null): mixed
{
if ($ttl <= 0) {
    return $callback();
}

try {
    $store = self::store();
    $fullKey = self::fullKey($store, $groups, $key);
    $hit = $store->get($fullKey);
} catch (Throwable $e) {
    self::failed($e);

    return $callback();
}

if ($hit !== null) {
    return $hit;
}

try {
    $lock = $store->lock($fullKey.':lock', self::LOCK_SECONDS);
    $lock->block(self::LOCK_WAIT_SECONDS);
} catch (LockTimeoutException) {
    return $callback();
} catch (Throwable $e) {
    self::failed($e);

    return $callback();
}

try {
    $again = self::safely(fn () => $store->get($fullKey));

    if ($again !== null) {
        return $again;
    }

    $value = $callback();
    $effective = ($isEmpty !== null && $isEmpty($value))
        ? min($ttl, max(1, (int) config('prices.cache.empty_ttl')))
        : $ttl;

    self::safely(fn () => $store->put($fullKey, $value, self::withJitter($effective)));

    return $value;
} finally {
    self::safely(fn () => $lock->release());
}
}
public static function lookup(string $key, Closure $callback, bool $paged = true): array
{
    $page = $paged ? Paginator::resolveCurrentPage() : 1;

    if ($page > (int) config('prices.cache.max_page')) {
        return $callback();
    }

    return self::remember(
        [self::LOOKUPS],
        $key.':'.$page,
        (int) config('prices.cache.lookups_ttl'),
        $callback,
        fn (array $value) => ($value['data'] ?? $value) === [],
    );
}

public static function bump(string ...$groups): void
{
self::safely(function () use ($groups) {
    $store = self::store();

    foreach ($groups as $group) {
        $store->put(self::versionKey($group), self::newToken(), now()->addDays(self::VERSION_TTL_DAYS));
    }
});
}

public static function pricesChanged(): void
{
self::bump(self::PRICES);
}

public static function productsChanged(): void
{
self::bump(self::PRICES, self::PRODUCTS);
}

public static function marketChanged(): void
{
self::bump(self::MARKET);
}

public static function placesChanged(): void
{
self::bump(self::PLACES);
}

public static function lookupsChanged(): void
{
self::bump(self::LOOKUPS, self::MARKET, self::PLACES);
}

public static function store(): Repository
{
$name = config('prices.cache.store');

return Cache::store(is_string($name) && $name !== '' ? $name : null);
}

private static function fullKey(Repository $store, array $groups, string $key): string
{
$parts = [];

foreach ($groups as $group) {
    $parts[] = $group.'='.self::version($store, $group);
}

return 'api-cache:'.implode(',', $parts).':'.$key;
}

private static function version(Repository $store, string $group): string
{
$key = self::versionKey($group);
$version = $store->get($key);

if (! is_string($version) || $version === '') {
    $version = self::newToken();
    $store->add($key, $version, now()->addDays(self::VERSION_TTL_DAYS));
    $stored = $store->get($key);
    $version = is_string($stored) && $stored !== '' ? $stored : $version;
}

return $version;
}

private static function versionKey(string $group): string
{
return 'api-cache:version:'.$group;
}

private static function newToken(): string
{
return Str::lower(Str::random(10));
}

private static function withJitter(int $ttl): int
{
return $ttl + random_int(0, max(0, intdiv($ttl, 10)));
}

private static function safely(Closure $callback): mixed
{
try {
    return $callback();
} catch (Throwable $e) {
    self::failed($e);

    return null;
}
}

private static function failed(Throwable $e): void
{
report($e);
}
}