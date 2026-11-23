<?php

namespace Tests\Feature;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use RuntimeException;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

/**
 * لو الكاش (Redis مثلاً) وقع، شريط الأسعار لازم يضل شغّال من الداتابيز بدل 500.
 * نفس سلوك ApiCache::remember اللي بيسجّل الخطأ وبيرجع للاستعلام.
 */
class PriceTickerCacheDownTest extends TestCase
{
    use BuildsProductPrices;
    use RefreshDatabase;

    public function test_ticker_is_served_from_the_database_when_the_cache_read_fails(): void
    {
        Exceptions::fake();

        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user);
        $this->makePrice($product, $lookups, $user, 'approved', ['product_price_value' => 5]);

        // الكاش بيجي من ApiCache::store() (Cache::store())، فبنخلي الـ store نفسه هو اللي يفشل بالقراءة
        $broken = Mockery::mock(Repository::class)->shouldIgnoreMissing();
        $broken->shouldReceive('get')->andThrow(new RuntimeException('cache down'));
        Cache::partialMock()->shouldReceive('store')->andReturn($broken);

        $prices = collect($this->getJson('/api/prices/ticker')->assertOk()->json('data'))
            ->pluck('price', 'product_id')
            ->all();

        $this->assertSame([$product->id => '5.00'], $prices);
        Exceptions::assertReported(RuntimeException::class);
    }

    public function test_ticker_is_served_from_the_database_when_the_cache_fails_after_a_successful_read(): void
    {
        Exceptions::fake();

        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user);
        $this->makePrice($product, $lookups, $user, 'approved', ['product_price_value' => 5]);

        // القراءة بترجع فاضي (miss) لكن القفل/الكتابة بيفشلوا: Redis وقع بنص الطلب
        $broken = Mockery::mock(Repository::class)->shouldIgnoreMissing();
        $broken->shouldReceive('get')->andReturn(null);
        $broken->shouldReceive('lock')->andThrow(new RuntimeException('cache down'));
        Cache::partialMock()->shouldReceive('store')->andReturn($broken);

        $prices = collect($this->getJson('/api/prices/ticker')->assertOk()->json('data'))
            ->pluck('price', 'product_id')
            ->all();

        $this->assertSame([$product->id => '5.00'], $prices);
        Exceptions::assertReported(RuntimeException::class);
    }
}