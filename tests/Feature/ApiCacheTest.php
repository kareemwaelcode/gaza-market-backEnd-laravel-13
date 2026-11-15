<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\User;
use App\Support\ApiCache;
use App\Support\PriceTicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Predis\Connection\Resource\Exception\StreamInitException;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class ApiCacheTest extends TestCase
{
    use BuildsProductPrices;
    use RefreshDatabase;

    /** @return array{0: array, 1: User, 2: Product, 3: ProductPrice} */
    private function warm(): array
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser();
        $product = $this->makeProduct($lookups, $owner);
        $price = $this->makePrice($product, $lookups, $owner);

        return [$lookups, $owner, $product, $price];
    }

    private function feedIds(): array
    {
        return collect($this->getJson('/api/prices')->assertOk()->json('data'))->pluck('price_id')->all();
    }

    private function searchIds(string $q = 'sugar'): array
    {
        return collect($this->getJson('/api/products/search?'.http_build_query(['q' => $q]))->assertOk()->json('data'))->pluck('id')->all();
    }

    private function queriesDuring(callable $callback): array
    {
        DB::flushQueryLog();
        DB::enableQueryLog();
        $callback();
        $queries = DB::getQueryLog();
        DB::flushQueryLog();

        return $queries;
    }

    // ---------- الـ feed ----------

    public function test_second_feed_request_is_served_from_cache_with_no_queries(): void
    {
        [, , , $price] = $this->warm();

        $first = $this->getJson('/api/prices')->assertOk()->getContent();

        $queries = $this->queriesDuring(function () use (&$second) {
            $second = $this->getJson('/api/prices')->assertOk()->getContent();
        });

        $this->assertSame([], $queries);
        $this->assertSame($first, $second);
        $this->assertSame([$price->id], collect(json_decode($second, true)['data'])->pluck('price_id')->all());
    }

    public function test_different_filters_and_pages_are_cached_separately(): void
    {
        [$lookups, $owner, $product] = $this->warm();
        $this->makePrice($product, $lookups, $owner);

        $this->getJson('/api/prices?limit=1&page=1')->assertOk()->assertJsonCount(1, 'data');
        $page2 = $this->getJson('/api/prices?limit=1&page=2')->assertOk();
        $unknown = $this->getJson('/api/prices?region=nope')->assertOk();

        $this->assertNotEmpty($page2->json('data'));
        $this->assertSame([], $unknown->json('data'));
        $this->assertNotSame(
            $this->getJson('/api/prices?limit=1&page=1')->json('data.0.price_id'),
            $this->getJson('/api/prices?limit=1&page=2')->json('data.0.price_id'),
        );
    }

    public function test_pages_beyond_the_cache_limit_are_not_cached(): void
    {
        $this->warm();
        config(['prices.cache.max_page' => 1]);

        $this->getJson('/api/prices?page=2')->assertOk();
        $queries = $this->queriesDuring(fn () => $this->getJson('/api/prices?page=2')->assertOk());

        $this->assertNotEmpty($queries);
    }

    public function test_a_zero_ttl_disables_the_feed_cache(): void
    {
        $this->warm();
        config(['prices.cache.feed_ttl' => 0]);

        $this->getJson('/api/prices')->assertOk();
        $queries = $this->queriesDuring(fn () => $this->getJson('/api/prices')->assertOk());

        $this->assertNotEmpty($queries);
    }

    public function test_an_empty_feed_is_cached_only_for_the_short_empty_ttl(): void
    {
        config(['prices.cache.empty_ttl' => 30]);

        $this->assertSame([], $this->feedIds());

        // بدون إبطال (إدخال مباشر بالداتابيز): لسا فاضي جوّا مدة الكاش
        [, , , $price] = $this->warm();
        $this->assertSame([], $this->feedIds());

        $this->travel(2)->minutes();
        $this->assertSame([$price->id], $this->feedIds());
    }

    public function test_the_feed_has_no_per_user_data_so_one_cached_copy_serves_everyone(): void
    {
        $this->warm();
        $guest = $this->getJson('/api/prices')->assertOk()->getContent();

        $queries = $this->queriesDuring(function () use (&$asUser) {
            $asUser = $this->actingAs($this->makeUser())->getJson('/api/prices')->assertOk()->getContent();
        });

        $this->assertSame($guest, $asUser);
        $this->assertStringNotContainsString('my_vote', $asUser);
    }

    // ---------- إبطال الـ feed ----------

    public function test_a_new_user_price_shows_up_in_the_feed_immediately(): void
    {
        [$lookups, , $product, $price] = $this->warm();
        $this->assertSame([$price->id], $this->feedIds());

        $newId = $this->actingAs($this->makeUser())->postJson('/api/prices', [
            'product_id' => $product->id,
            'region' => $lookups['area']->area_slug,
            'price' => 6.5,
            'store_name' => 'بقالة جديدة',
        ])->assertCreated()->json('data.id');

        $this->assertEqualsCanonicalizing([$price->id, $newId], $this->feedIds());
    }

    public function test_rejecting_and_approving_a_price_from_the_cms_updates_the_feed(): void
    {
        [, , , $price] = $this->warm();
        $this->assertSame([$price->id], $this->feedIds());

        $this->postJson(route('product_price_reject', $price->id))->assertOk();
        $this->assertSame([], $this->feedIds());

        $this->postJson(route('product_price_approve', $price->id))->assertOk();
        $this->assertSame([$price->id], $this->feedIds());
    }

    public function test_deleting_a_price_from_the_cms_updates_the_feed(): void
    {
        [, , , $price] = $this->warm();
        $this->assertSame([$price->id], $this->feedIds());

        $this->deleteJson(route('product_price.destroy', $price->id))->assertOk();

        $this->assertSame([], $this->feedIds());
    }

    public function test_hiding_a_product_updates_both_the_feed_and_the_search(): void
    {
        [, , $product, $price] = $this->warm();
        $this->assertSame([$price->id], $this->feedIds());
        $this->assertSame([$product->id], $this->searchIds());

        $this->postJson(route('product_hide', $product->id))->assertOk();

        $this->assertSame([], $this->feedIds());
        $this->assertSame([], $this->searchIds());

        $this->postJson(route('product_unhide', $product->id))->assertOk();

        $this->assertSame([$price->id], $this->feedIds());
        $this->assertSame([$product->id], $this->searchIds());
    }

    public function test_a_price_hidden_by_reports_disappears_from_the_cached_feed(): void
    {
        [, , , $price] = $this->warm();
        $this->assertSame([$price->id], $this->feedIds());

        for ($i = 0; $i <= ProductPrice::MAX_REPORTS; $i++) {
            $this->actingAs($this->makeUser())
                ->postJson(route('prices.vote', $price->id), ['vote' => 'report'])
                ->assertOk();
        }

        $this->assertSame([], $this->feedIds());
    }

    public function test_votes_do_not_invalidate_the_feed_but_the_vote_response_is_fresh(): void
    {
        [, , , $price] = $this->warm();
        $this->assertSame(0, $this->getJson('/api/prices')->json('data.0.confirm_count'));

        $this->actingAs($this->makeUser())
            ->postJson(route('prices.vote', $price->id), ['vote' => 'confirm'])
            ->assertOk()
            ->assertJsonPath('data.confirm_count', 1);

        // القرار المتفق عليه: العدّادات بالـ feed بتتأخر لحد feed_ttl
        $this->assertSame(0, $this->getJson('/api/prices')->json('data.0.confirm_count'));

        $this->travel(2)->minutes();
        $this->assertSame(1, $this->getJson('/api/prices')->json('data.0.confirm_count'));
    }

    // ---------- البحث ----------

    public function test_second_search_is_served_from_cache_and_the_term_is_normalized(): void
    {
        [, , $product] = $this->warm();

        $this->assertSame([$product->id], $this->searchIds('SUGAR'));

        $queries = $this->queriesDuring(function () use ($product) {
            $this->assertSame([$product->id], $this->searchIds('sugar'));
            $this->assertSame([$product->id], $this->searchIds('Sugar'));
        });

        $this->assertSame([], $queries);
    }

    public function test_search_pages_beyond_the_cache_limit_are_not_cached(): void
    {
        $this->warm();
        config(['prices.cache.max_page' => 1]);

        $uri = '/api/products/search?q=sugar&page=2';
        $this->getJson($uri)->assertOk();
        $queries = $this->queriesDuring(fn () => $this->getJson($uri)->assertOk());

        $this->assertNotEmpty($queries);
    }

    public function test_an_empty_search_is_cached_only_for_the_short_empty_ttl(): void
    {
        config(['prices.cache.empty_ttl' => 30]);
        $this->assertSame([], $this->searchIds('zzzz'));

        $lookups = $this->lookups();
        $product = $this->makeProduct($lookups, $this->makeUser(), 'active', ['product_name_en' => 'zzzz-item']);

        $this->assertSame([], $this->searchIds('zzzz'));

        $this->travel(2)->minutes();
        $this->assertSame([$product->id], $this->searchIds('zzzz'));
    }

    // ---------- الشريط ----------

    public function test_ticker_uses_the_configured_cache_store(): void
    {
        config(['cache.stores.second' => ['driver' => 'array'], 'prices.cache.store' => 'second']);
        $this->warm();

        $this->getJson('/api/prices/ticker')->assertOk()->assertJsonCount(1, 'data');

        $this->assertTrue(Cache::store('second')->has(PriceTicker::KEY));
        $this->assertFalse(Cache::store('array')->has(PriceTicker::KEY));
    }

    // ---------- الأعطال ----------

    public function test_the_feed_and_search_still_work_when_the_cache_store_is_down(): void
    {
        Exceptions::fake();
        [, , $product, $price] = $this->warm();

        // Redis مغلق (بورت ما حدا سامع عليه): كل عملية كاش بترمي استثناء
        config([
            'database.redis.client' => 'predis',
            'database.redis.broken' => ['host' => '127.0.0.1', 'port' => 1, 'database' => 0],
            'cache.stores.broken' => ['driver' => 'redis', 'connection' => 'broken', 'lock_connection' => 'broken'],
            'prices.cache.store' => 'broken',
        ]);

        $this->assertSame([$price->id], $this->feedIds());
        $this->assertSame([$product->id], $this->searchIds());

        Exceptions::assertReported(StreamInitException::class);
    }

    public function test_a_failing_bump_never_breaks_a_moderation_action(): void
    {
        Exceptions::fake();
        [, , , $price] = $this->warm();

        config([
            'database.redis.client' => 'predis',
            'database.redis.broken' => ['host' => '127.0.0.1', 'port' => 1, 'database' => 0],
            'cache.stores.broken' => ['driver' => 'redis', 'connection' => 'broken', 'lock_connection' => 'broken'],
            'prices.cache.store' => 'broken',
        ]);

        $this->postJson(route('product_price_reject', $price->id))->assertOk();

        $this->assertSame('rejected', $price->refresh()->product_price_status);
    }

    // ---------- الـ helper ----------

    public function test_bump_only_invalidates_keys_that_depend_on_the_bumped_group(): void
    {
        $builds = ['prices' => 0, 'products' => 0];
        $make = function (string $group) use (&$builds) {
            return function () use (&$builds, $group) {
                return ['n' => ++$builds[$group]];
            };
        };

        ApiCache::remember([ApiCache::PRICES], 'k', 60, $make('prices'));
        ApiCache::remember([ApiCache::PRODUCTS], 'k', 60, $make('products'));
        ApiCache::remember([ApiCache::PRICES], 'k', 60, $make('prices'));
        $this->assertSame(['prices' => 1, 'products' => 1], $builds);

        ApiCache::pricesChanged();

        ApiCache::remember([ApiCache::PRICES], 'k', 60, $make('prices'));
        ApiCache::remember([ApiCache::PRODUCTS], 'k', 60, $make('products'));
        $this->assertSame(['prices' => 2, 'products' => 1], $builds);

        ApiCache::productsChanged();

        ApiCache::remember([ApiCache::PRICES], 'k', 60, $make('prices'));
        ApiCache::remember([ApiCache::PRODUCTS], 'k', 60, $make('products'));
        $this->assertSame(['prices' => 3, 'products' => 2], $builds);
    }
}