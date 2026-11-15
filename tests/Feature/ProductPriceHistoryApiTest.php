<?php

namespace Tests\Feature;

use App\Support\ApiCache;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class ProductPriceHistoryApiTest extends TestCase
{
    use BuildsProductPrices;
    use RefreshDatabase;

    public function test_it_is_public_returns_only_approved_prices_newest_first_and_opens_no_session(): void
    {
        config(['session.driver' => 'database']);

        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user);

        $a = $this->makePrice($product, $lookups, $user, 'approved', ['created_at' => now()->subDay()]);
        $b = $this->makePrice($product, $lookups, $user, 'approved', ['created_at' => now()]);
        $this->makePrice($product, $lookups, $user, 'pending');
        $this->makePrice($product, $lookups, $user, 'rejected');
        $this->makePrice($product, $lookups, $user, 'flagged');

        $response = $this->getJson("/api/products/{$product->id}/prices")->assertOk();

        $this->assertSame([$b->id, $a->id], collect($response->json('data'))->pluck('price_id')->all());
        $this->assertSame(2, $response->json('total'));
        $this->assertSame('sugar', strtolower($response->json('product.name_en')));
        $this->assertSame([], $response->headers->getCookies());
        $this->assertSame(0, DB::table('sessions')->count());
    }

    public function test_identical_active_products_are_merged_but_different_ones_are_not(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();

        $first = $this->makeProduct($lookups, $user);
        $twin = $this->makeProduct($lookups, $user);                                   // نفس الاسم والكمية والوحدة
        $otherQty = $this->makeProduct($lookups, $user, 'active', ['product_quantity' => 5]);
        $hiddenTwin = $this->makeProduct($lookups, $user, 'hidden');

        $p1 = $this->makePrice($first, $lookups, $user);
        $p2 = $this->makePrice($twin, $lookups, $user);
        $this->makePrice($otherQty, $lookups, $user);
        $this->makePrice($hiddenTwin, $lookups, $user);

        $ids = collect($this->getJson("/api/products/{$first->id}/prices")->assertOk()->json('data'))
            ->pluck('price_id')->sort()->values()->all();

        $this->assertSame([$p1->id, $p2->id], $ids);
    }

    public function test_unknown_or_not_active_products_are_404(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $pending = $this->makeProduct($lookups, $user, 'pending');

        $this->getJson('/api/products/999999/prices')->assertStatus(404);
        $this->getJson("/api/products/{$pending->id}/prices")->assertStatus(404);
        $this->getJson('/api/products/abc/prices')->assertStatus(404);
    }

    public function test_it_never_exposes_phone_receipt_or_user(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user);
        $this->makePrice($product, $lookups, $user, 'approved', [
            'product_price_store_phone' => '0591234567',
            'product_price_receipt_image' => 'receipts/secret.jpg',
        ]);

        $body = $this->getJson("/api/products/{$product->id}/prices")->assertOk()->getContent();

        foreach (['0591234567', 'secret.jpg', 'user_id', 'receipt'] as $needle) {
            $this->assertStringNotContainsString($needle, $body);
        }
    }

    public function test_cache_is_invalidated_when_prices_change(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user);
        $this->makePrice($product, $lookups, $user);

        $this->getJson("/api/products/{$product->id}/prices")->assertJsonPath('total', 1);

        $this->makePrice($product, $lookups, $user);
        ApiCache::pricesChanged();

        $this->getJson("/api/products/{$product->id}/prices")->assertJsonPath('total', 2);
    }

    public function test_pagination_and_validation(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user);

        foreach (range(1, 3) as $i) {
            $this->makePrice($product, $lookups, $user);
        }

        $this->getJson("/api/products/{$product->id}/prices?limit=2")
            ->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('has_more', true);
        $this->getJson("/api/products/{$product->id}/prices?limit=99")->assertStatus(400);
    }
}