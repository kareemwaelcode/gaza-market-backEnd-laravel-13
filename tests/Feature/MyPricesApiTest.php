<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class MyPricesApiTest extends TestCase
{
    use BuildsProductPrices;
    use RefreshDatabase;

    public function test_guest_gets_401(): void
    {
        $this->getJson('/api/me/prices')->assertStatus(401);
    }

    public function test_it_returns_only_my_prices_in_every_status_newest_first(): void
    {
        $lookups = $this->lookups();
        $me = $this->makeUser();
        $other = $this->makeUser();
        $product = $this->makeProduct($lookups, $other);

        $old = $this->makePrice($product, $lookups, $me, 'approved', ['created_at' => now()->subDays(2)]);
        $pending = $this->makePrice($product, $lookups, $me, 'pending', ['created_at' => now()->subDay()]);
        $rejected = $this->makePrice($product, $lookups, $me, 'rejected', [
            'rejection_reason' => 'سعر غير منطقي',
            'created_at' => now(),
        ]);
        $this->makePrice($product, $lookups, $other);

        $response = $this->actingAs($me)->getJson('/api/me/prices')->assertOk();

        $this->assertSame([$rejected->id, $pending->id, $old->id], collect($response->json('data'))->pluck('price_id')->all());
        $this->assertSame(['rejected', 'pending', 'approved'], collect($response->json('data'))->pluck('status')->all());
        $this->assertSame('سعر غير منطقي', $response->json('data.0.rejection_reason'));
        $this->assertSame(3, $response->json('total'));
        $this->assertFalse($response->json('has_more'));
    }

    public function test_it_never_leaks_another_users_prices_even_if_user_id_is_sent(): void
    {
        $lookups = $this->lookups();
        $me = $this->makeUser();
        $other = $this->makeUser();
        $product = $this->makeProduct($lookups, $other);
        $this->makePrice($product, $lookups, $other);

        $this->actingAs($me)->getJson('/api/me/prices?user_id='.$other->id)
            ->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('total', 0);
    }

    public function test_it_hides_private_fields(): void
    {
        $lookups = $this->lookups();
        $me = $this->makeUser();
        $product = $this->makeProduct($lookups, $me);
        $this->makePrice($product, $lookups, $me, 'approved', [
            'product_price_store_phone' => '0591234567',
            'product_price_receipt_image' => 'receipts/secret.jpg',
        ]);

        $item = $this->actingAs($me)->getJson('/api/me/prices')->assertOk()->json('data.0');

        foreach (['user_id', 'store_phone', 'product_price_store_phone', 'receipt_image', 'product_price_receipt_image'] as $key) {
            $this->assertArrayNotHasKey($key, $item);
        }
        $this->assertStringNotContainsString('secret.jpg', json_encode($item));
    }

    public function test_pagination_and_validation(): void
    {
        $lookups = $this->lookups();
        $me = $this->makeUser();
        $product = $this->makeProduct($lookups, $me);

        foreach (range(1, 3) as $i) {
            $this->makePrice($product, $lookups, $me);
        }

        $this->actingAs($me)->getJson('/api/me/prices?limit=2')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('total', 3);

        $this->actingAs($me)->getJson('/api/me/prices?limit=2&page=2')->assertOk()->assertJsonCount(1, 'data');
        $this->actingAs($me)->getJson('/api/me/prices?limit=51')->assertStatus(400);
        $this->actingAs($me)->getJson('/api/me/prices?page=0')->assertStatus(400);
    }

    public function test_suspended_user_is_logged_out(): void
    {
        $me = $this->makeUser();
        $me->forceFill(['user_status' => 'suspended'])->save();

        $this->actingAs($me)->getJson('/api/me/prices')->assertStatus(401);
    }
}