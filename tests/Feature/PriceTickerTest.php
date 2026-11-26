<?php

namespace Tests\Feature;

use App\Models\ProductPrice;
use App\Support\PriceTicker;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Exceptions;
use Mockery;
use RuntimeException;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class PriceTickerTest extends TestCase
{
use BuildsProductPrices;
use RefreshDatabase;

private function ticker()
{
return $this->getJson('/api/prices/ticker');
}

private function ids(): array
{
return collect($this->ticker()->assertOk()->json('data'))->pluck('product_id')->all();
}

private function prices(): array
{
return collect($this->ticker()->assertOk()->json('data'))->pluck('price', 'product_id')->all();
}

private function queriesDuring(callable $callback): array
{
DB::flushQueryLog();
DB::enableQueryLog();
$callback();

return DB::getQueryLog();
}


public function test_ticker_is_public_and_shows_only_the_product_and_its_price(): void
{
$lookups = $this->lookups('gaza-rimal');
$user = $this->makeUser();
$product = $this->makeProduct($lookups, $user, 'active', ['product_name_ar' => 'سكر', 'product_name_en' => 'Sugar', 'product_quantity' => 2]);
$this->makePrice($product, $lookups, $user, 'approved', [
    'product_price_value' => 8.5,
    'product_price_store_phone' => '0599000000',
    'product_price_receipt_image' => 'receipts/secret.jpg',
]);

$response = $this->ticker()
    ->assertOk()
    ->assertJsonCount(1, 'data')
    ->assertJsonPath('data.0.product_id', $product->id)
    ->assertJsonPath('data.0.product_name_ar', 'سكر')
    ->assertJsonPath('data.0.product_name_en', 'Sugar')
    ->assertJsonPath('data.0.price', '8.50');

$this->assertSame(
    ['product_id', 'product_name_ar', 'product_name_en', 'price'],
    array_keys($response->json('data.0'))
);
}

public function test_ticker_shows_only_the_latest_approved_price_of_each_active_product(): void
{
$lookups = $this->lookups();
$user = $this->makeUser();
$active = $this->makeProduct($lookups, $user, 'active');
$other = $this->makeProduct($lookups, $user, 'active', ['product_name_ar' => 'عدس']);
$hidden = $this->makeProduct($lookups, $user, 'hidden');

$this->makePrice($active, $lookups, $user, 'approved', ['product_price_value' => 5]);
$this->makePrice($active, $lookups, $user, 'approved', ['product_price_value' => 6]);
$this->makePrice($active, $lookups, $user, 'pending', ['product_price_value' => 90]);
$this->makePrice($active, $lookups, $user, 'rejected', ['product_price_value' => 91]);
$this->makePrice($active, $lookups, $user, 'flagged', ['product_price_value' => 92]);
$this->makePrice($hidden, $lookups, $user, 'approved');
$this->makePrice($other, $lookups, $user, 'approved', ['product_price_value' => 4]);

$this->assertSame([$other->id, $active->id], $this->ids());
$this->assertSame([$other->id => '4.00', $active->id => '6.00'], $this->prices());
}

public function test_ticker_falls_back_to_the_previous_price_when_the_latest_is_hidden(): void
{
$lookups = $this->lookups();
$user = $this->makeUser();
$product = $this->makeProduct($lookups, $user);

$previous = $this->makePrice($product, $lookups, $user, 'approved', ['product_price_value' => 5]);
$latest = $this->makePrice($product, $lookups, $user, 'approved', ['product_price_value' => 7]);

$this->assertSame([$product->id => '7.00'], $this->prices());

$this->postJson(route('product_price_reject', $latest->id))->assertOk();
$this->assertSame([$product->id => '5.00'], $this->prices());

$this->deleteJson(route('product_price.destroy', $previous->id))->assertOk();
$this->assertSame([], $this->ids());
}

public function test_ticker_is_limited_by_config(): void
{
config(['prices.ticker.limit' => 3]);

$lookups = $this->lookups();
$user = $this->makeUser();

foreach (range(1, 5) as $i) {
    $this->makePrice($this->makeProduct($lookups, $user), $lookups, $user);
}

$this->ticker()->assertOk()->assertJsonCount(3, 'data');
}

public function test_ticker_is_empty_without_prices(): void
{
$this->ticker()->assertOk()->assertExactJson(['data' => []]);
}


public function test_second_request_is_served_from_cache_without_queries(): void
{
$lookups = $this->lookups();
$user = $this->makeUser();
$this->makePrice($this->makeProduct($lookups, $user), $lookups, $user);

$this->assertNotSame([], $this->queriesDuring(fn () => $this->ticker()->assertOk()));

$queries = $this->queriesDuring(fn () => $this->ticker()->assertOk()->assertJsonCount(1, 'data'));

$this->assertSame([], $queries);
}

public function test_visitors_never_hit_the_database_after_the_scheduled_refresh(): void
{
$lookups = $this->lookups();
$user = $this->makeUser();
$this->makePrice($this->makeProduct($lookups, $user), $lookups, $user);

$this->artisan('prices:refresh-ticker')->assertSuccessful();

$queries = $this->queriesDuring(function () {
    foreach (range(1, 5) as $i) {
        $this->ticker()->assertOk()->assertJsonCount(1, 'data');
    }
});

$this->assertSame([], $queries);
}

public function test_cache_can_be_turned_off_with_zero_ttl(): void
{
config(['prices.ticker.ttl' => 0]);

$lookups = $this->lookups();
$user = $this->makeUser();
$product = $this->makeProduct($lookups, $user);
$this->makePrice($product, $lookups, $user, 'approved', ['product_price_value' => 5]);

$this->assertSame([$product->id => '5.00'], $this->prices());

$this->makePrice($product, $lookups, $user, 'approved', ['product_price_value' => 9]);

$this->assertSame([$product->id => '9.00'], $this->prices());
$this->assertFalse(Cache::has(PriceTicker::KEY));
}

public function test_forget_clears_only_the_ticker_key(): void
{
$lookups = $this->lookups();
$user = $this->makeUser();
$this->makePrice($this->makeProduct($lookups, $user), $lookups, $user);

Cache::put('otp:other-key', 'keep-me', 60);
$this->ticker()->assertOk();

PriceTicker::forget();

$this->assertSame('keep-me', Cache::get('otp:other-key'));
$this->assertFalse(Cache::has(PriceTicker::KEY));
}

public function test_the_cached_ticker_is_the_same_for_guests_and_logged_in_users(): void
{
$lookups = $this->lookups();
$user = $this->makeUser();
$voter = $this->makeUser();
$price = $this->makePrice($this->makeProduct($lookups, $user), $lookups, $user);
$this->makeVote($price, $voter, 'confirm');

$guest = $this->ticker()->assertOk()->json();
$member = $this->actingAs($voter)->getJson('/api/prices/ticker')->assertOk()->json();

$this->assertSame($guest, $member);
$this->assertArrayNotHasKey('my_vote', $member['data'][0]);
}

public function test_the_cache_key_is_new_so_the_old_payload_shape_is_never_served(): void
{
$lookups = $this->lookups();
$user = $this->makeUser();
$product = $this->makeProduct($lookups, $user);
$this->makePrice($product, $lookups, $user);

Cache::put('prices:ticker:v1', [['price_id' => 1, 'store_name' => 'old']], 600);

$this->ticker()->assertOk()->assertJsonMissingPath('data.0.store_name')->assertJsonPath('data.0.product_id', $product->id);
}


public function test_the_refresh_command_builds_the_cache_and_fresh_rebuilds_it(): void
{
$lookups = $this->lookups();
$user = $this->makeUser();
$product = $this->makeProduct($lookups, $user);
$this->makePrice($product, $lookups, $user, 'approved', ['product_price_value' => 5]);

$this->artisan('prices:refresh-ticker')->expectsOutputToContain('1 items')->assertSuccessful();
$this->assertCount(1, Cache::get(PriceTicker::KEY));

$this->makePrice($product, $lookups, $user, 'approved', ['product_price_value' => 6]);
$this->assertSame([$product->id => '5.00'], $this->prices());

$this->artisan('prices:refresh-ticker', ['--fresh' => true])->assertSuccessful();
$this->assertSame([$product->id => '6.00'], $this->prices());
}

public function test_the_refresh_command_does_nothing_when_the_cache_is_off(): void
{
config(['prices.ticker.ttl' => 0]);

$this->artisan('prices:refresh-ticker')->expectsOutputToContain('disabled')->assertSuccessful();
$this->assertFalse(Cache::has(PriceTicker::KEY));
}

public function test_the_refresh_command_warns_when_ttl_is_not_longer_than_the_interval(): void
{
config(['prices.ticker.ttl' => 60, 'prices.ticker.refresh_minutes' => 10]);

$this->artisan('prices:refresh-ticker')->expectsOutputToContain('must be longer')->assertSuccessful();
}

public function test_the_ticker_refresh_is_scheduled_every_interval_and_every_night(): void
{
Artisan::call('list');

$events = collect(app(Schedule::class)->events())
    ->filter(fn ($event) => str_contains($event->command, 'prices:refresh-ticker'));

$periodic = $events->first(fn ($event) => ! str_contains($event->command, '--fresh'));
$nightly = $events->first(fn ($event) => str_contains($event->command, '--fresh'));

$this->assertNotNull($periodic);
$this->assertSame('*/10 * * * *', $periodic->expression);
$this->assertTrue($periodic->withoutOverlapping);
$this->assertTrue($periodic->onOneServer);

$this->assertNotNull($nightly);
$this->assertSame('5 3 * * *', $nightly->expression);
$this->assertSame('Asia/Gaza', $nightly->timezone);
$this->assertTrue($nightly->withoutOverlapping);
$this->assertTrue($nightly->onOneServer);
}


private function warm(): array
{
$lookups = $this->lookups();
$owner = $this->makeUser();
$product = $this->makeProduct($lookups, $owner);
$price = $this->makePrice($product, $lookups, $owner);

$this->assertSame([$product->id => '7.50'], $this->prices());

return [$lookups, $owner, $product, $price];
}

public function test_a_new_price_from_the_api_stays_out_of_the_ticker_until_it_is_approved(): void
{
[$lookups, $owner, $product] = $this->warm();

$newId = $this->actingAs($this->makeUser())->postJson('/api/prices', [
    'product_id' => $product->id,
    'region' => $lookups['area']->area_slug,
    'price' => 6,
    'store_name' => 'متجر جديد',
])->assertCreated()->json('data.id');

$this->assertTrue(Cache::has(PriceTicker::KEY));
$queries = $this->queriesDuring(fn () => $this->assertSame([$product->id => '7.50'], $this->prices()));
$this->assertSame([], $queries);

$this->artisan('prices:refresh-ticker')->assertSuccessful();
$this->assertSame([$product->id => '7.50'], $this->prices());

$this->postJson(route('product_price_approve', $newId))->assertOk();
$this->artisan('prices:refresh-ticker')->assertSuccessful();
$this->assertSame([$product->id => '6.00'], $this->prices());
}

public function test_a_price_flagged_by_reports_leaves_the_ticker_immediately(): void
{
[, , , $price] = $this->warm();

foreach (range(1, 6) as $i) {
    $this->actingAs($this->makeUser())
        ->postJson(route('prices.vote', $price->id), ['vote' => 'report'])
        ->assertOk();
}

$this->assertSame('flagged', $price->refresh()->product_price_status);
$this->assertSame([], $this->ids());
}

public function test_moderation_refreshes_the_cache_itself_so_visitors_do_not_rebuild_it(): void
{
[, , , $price] = $this->warm();

$this->postJson(route('product_price_reject', $price->id))->assertOk();

$this->assertTrue(Cache::has(PriceTicker::KEY));
$this->assertSame([], $this->queriesDuring(fn () => $this->assertSame([], $this->ids())));
}

public function test_a_cache_failure_after_a_moderation_change_does_not_break_the_response(): void
{
Exceptions::fake();

[, , , $price] = $this->warm();

$broken = Mockery::mock(Repository::class)->shouldIgnoreMissing();
$broken->shouldReceive('put')->andThrow(new RuntimeException('cache down'));
Cache::partialMock()->shouldReceive('store')->andReturn($broken);

$this->postJson(route('product_price_reject', $price->id))->assertOk();

Exceptions::assertReported(RuntimeException::class);
$this->assertSame('rejected', $price->refresh()->product_price_status);
}

public function test_votes_that_do_not_hide_the_price_leave_the_cache_alone(): void
{
[, , , $price] = $this->warm();

DB::flushQueryLog();
DB::enableQueryLog();

$this->actingAs($this->makeUser())
    ->postJson(route('prices.vote', $price->id), ['vote' => 'confirm'])
    ->assertOk();
DB::flushQueryLog();

$this->ticker()->assertOk();

$this->assertSame([], DB::getQueryLog());
}

public function test_hiding_and_showing_a_price_from_the_cms_updates_the_ticker(): void
{
[, , , $price] = $this->warm();

$this->postJson(route('product_price_reject', $price->id))->assertOk();
$this->assertSame([], $this->ids());

$this->postJson(route('product_price_approve', $price->id))->assertOk();
$this->assertSame([$price->product_id => '7.50'], $this->prices());
}

public function test_deleting_a_price_from_the_cms_updates_the_ticker(): void
{
[, , , $price] = $this->warm();

$this->deleteJson(route('product_price.destroy', $price->id))->assertOk();

$this->assertSame([], $this->ids());
}

public function test_hiding_and_showing_the_product_updates_the_ticker(): void
{
[, , $product] = $this->warm();

$this->postJson(route('product_hide', $product->id))->assertOk();
$this->assertSame([], $this->ids());

$this->postJson(route('product_unhide', $product->id))->assertOk();
$this->assertSame([$product->id => '7.50'], $this->prices());
}

public function test_deleting_a_product_from_the_cms_updates_the_ticker(): void
{
[, , $product] = $this->warm();

$this->deleteJson(route('product.destroy', $product->id))->assertOk();

$this->assertSame([], $this->ids());
}

public function test_approving_a_new_product_puts_its_first_price_in_the_ticker(): void
{
[$lookups, $owner, $product] = $this->warm();

$pending = $this->makeProduct($lookups, $owner, 'pending', ['product_name_ar' => 'عدس']);

$this->postJson(route('product_approve', $pending->id))->assertOk();

$ids = $this->ids();
$this->assertCount(2, $ids);
$this->assertContains($product->id, $ids);
$this->assertContains($pending->id, $ids);
$this->assertNotNull(ProductPrice::where('product_id', $pending->id)->value('id'));
}

public function test_the_backfill_command_refreshes_the_ticker(): void
{
[$lookups, $owner, $product] = $this->warm();

$old = $this->makeProduct($lookups, $owner, 'active', ['product_name_ar' => 'قديم']);

$this->artisan('prices:backfill-first')->assertSuccessful();

$this->assertSame([$old->id => '8.00', $product->id => '8.00'], $this->prices());
$this->assertCount(2, $this->ids());
}
}