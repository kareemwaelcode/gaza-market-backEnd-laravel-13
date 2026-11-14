<?php

namespace Tests\Feature;

use App\Models\ProductPrice;
use App\Support\PriceTicker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
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
        return collect($this->ticker()->assertOk()->json('data'))->pluck('price_id')->all();
    }

    // ---------- المحتوى ----------

    public function test_ticker_is_public_and_has_the_card_fields_only(): void
    {
        $lookups = $this->lookups('gaza-rimal');
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user, 'active', ['product_name_ar' => 'سكر', 'product_quantity' => 2]);
        $price = $this->makePrice($product, $lookups, $user, 'approved', [
            'product_price_value' => 8.5,
            'product_price_store_phone' => '0599000000',
            'product_price_receipt_image' => 'receipts/secret.jpg',
        ]);

        $response = $this->ticker()
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price_id', $price->id)
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonPath('data.0.product_name_ar', 'سكر')
            ->assertJsonPath('data.0.price', '8.50')
            ->assertJsonPath('data.0.store_name', 'بقالة أبو محمد')
            ->assertJsonPath('data.0.region', 'gaza-rimal')
            ->assertJsonPath('data.0.unit', $lookups['unit']->unit_slug);

        foreach (['my_vote', 'confirm_count', 'report_count', 'user_id', 'store_phone', 'product_price_store_phone', 'product_price_receipt_image'] as $field) {
            $response->assertJsonMissingPath('data.0.'.$field);
        }
    }

    public function test_ticker_shows_only_the_latest_approved_price_of_each_active_product(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $active = $this->makeProduct($lookups, $user, 'active');
        $other = $this->makeProduct($lookups, $user, 'active', ['product_name_ar' => 'عدس']);
        $hidden = $this->makeProduct($lookups, $user, 'hidden');

        $this->makePrice($active, $lookups, $user, 'approved');
        $latest = $this->makePrice($active, $lookups, $user, 'approved');
        $this->makePrice($active, $lookups, $user, 'pending');
        $this->makePrice($active, $lookups, $user, 'rejected');
        $this->makePrice($active, $lookups, $user, 'flagged');
        $this->makePrice($hidden, $lookups, $user, 'approved');
        $otherPrice = $this->makePrice($other, $lookups, $user, 'approved');

        // منتج واحد = سعر واحد، الأحدث بالأول
        $this->assertSame([$otherPrice->id, $latest->id], $this->ids());
    }

    public function test_ticker_falls_back_to_the_previous_price_when_the_latest_is_hidden(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user);

        $previous = $this->makePrice($product, $lookups, $user);
        $latest = $this->makePrice($product, $lookups, $user);

        $this->assertSame([$latest->id], $this->ids());

        $this->postJson(route('product_price_reject', $latest->id))->assertOk();
        $this->assertSame([$previous->id], $this->ids());

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

    // ---------- الكاش ----------

    public function test_second_request_is_served_from_cache_without_queries(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $this->makePrice($this->makeProduct($lookups, $user), $lookups, $user);

        $this->ticker()->assertOk();

        DB::flushQueryLog();
        DB::enableQueryLog();
        $this->ticker()->assertOk()->assertJsonCount(1, 'data');

        $this->assertSame([], DB::getQueryLog());
    }

    public function test_cache_can_be_turned_off_with_zero_ttl(): void
    {
        config(['prices.ticker.ttl' => 0]);

        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user);
        $first = $this->makePrice($product, $lookups, $user);

        $this->assertSame([$first->id], $this->ids());

        $second = $this->makePrice($product, $lookups, $user);

        // نفس المنتج: السعر الأحدث بيحل مكان القديم
        $this->assertSame([$second->id], $this->ids());
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
        $this->assertFalse(Cache::has('prices:ticker:v1'));
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

    // ---------- الإبطال ----------

    // يسخّن الكاش بسعر واحد ويرجّعه
    private function warm(): array
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser();
        $product = $this->makeProduct($lookups, $owner);
        $price = $this->makePrice($product, $lookups, $owner);

        $this->assertSame([$price->id], $this->ids());

        return [$lookups, $owner, $product, $price];
    }

    public function test_a_new_price_from_the_api_shows_up_immediately(): void
    {
        [$lookups, $owner, $product, $price] = $this->warm();

        $response = $this->actingAs($this->makeUser())->postJson('/api/prices', [
            'product_id' => $product->id,
            'region' => $lookups['area']->area_slug,
            'price' => 6,
            'store_name' => 'متجر جديد',
        ])->assertCreated();

        // نفس المنتج: السعر الجديد بيحل مكان القديم بالشريط
        $this->assertSame([$response->json('data.id')], $this->ids());
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
        $this->assertSame([$price->id], $this->ids());
    }

    public function test_deleting_a_price_from_the_cms_updates_the_ticker(): void
    {
        [, , , $price] = $this->warm();

        $this->deleteJson(route('product_price.destroy', $price->id))->assertOk();

        $this->assertSame([], $this->ids());
    }

    public function test_hiding_and_showing_the_product_updates_the_ticker(): void
    {
        [, , $product, $price] = $this->warm();

        $this->postJson(route('product_hide', $product->id))->assertOk();
        $this->assertSame([], $this->ids());

        $this->postJson(route('product_unhide', $product->id))->assertOk();
        $this->assertSame([$price->id], $this->ids());
    }

    public function test_deleting_a_product_from_the_cms_updates_the_ticker(): void
    {
        [, , $product] = $this->warm();

        $this->deleteJson(route('product.destroy', $product->id))->assertOk();

        $this->assertSame([], $this->ids());
    }

    public function test_approving_a_new_product_puts_its_first_price_in_the_ticker(): void
    {
        [$lookups, $owner, , $price] = $this->warm();

        $pending = $this->makeProduct($lookups, $owner, 'pending', ['product_name_ar' => 'عدس']);

        $this->postJson(route('product_approve', $pending->id))->assertOk();

        $ids = $this->ids();
        $this->assertCount(2, $ids);
        $this->assertContains($price->id, $ids);
        $this->assertContains(ProductPrice::where('product_id', $pending->id)->value('id'), $ids);
    }

    public function test_the_backfill_command_refreshes_the_ticker(): void
    {
        [$lookups, $owner, , $price] = $this->warm();

        $this->makeProduct($lookups, $owner, 'active', ['product_name_ar' => 'قديم']);

        $this->artisan('prices:backfill-first')->assertSuccessful();

        // الـ backfill بينشئ سعر أول للمنتج القديم وللمنتج المسخَّن (سعره المسجّل بقيم غير قيم صفّه)،
        // والشريط بيعرض آخر سعر لكل منتج: يعني سعرين بس، والسعر المسخَّن القديم ما عاد ظاهر
        $ids = $this->ids();
        $this->assertCount(2, $ids);
        $this->assertNotContains($price->id, $ids);
    }
}