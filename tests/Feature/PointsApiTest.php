<?php

namespace Tests\Feature;

use App\Models\PointLevel;
use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Models\ProductPrice;
use App\Services\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class PointsApiTest extends TestCase
{
    use BuildsProductPrices;
    use RefreshDatabase;

    private function addPriceRule(int $points = 10, ?int $limit = null): PointRule
    {
        return PointRule::create([
            'point_rule_key' => 'add_price',
            'point_rule_name_en' => 'Add a price',
            'point_rule_name_ar' => 'إضافة سعر',
            'point_rule_points' => $points,
            'point_rule_daily_limit' => $limit,
            'point_rule_is_active' => true,
        ]);
    }

    private function level(string $key, int $min): PointLevel
    {
        return PointLevel::create([
            'point_level_key' => $key,
            'point_level_name_en' => ucfirst($key),
            'point_level_name_ar' => $key,
            'point_level_min_points' => $min,
        ]);
    }

    private function storePrice($user, array $lookups, int $productId, array $extra = [])
    {
        return $this->actingAs($user)->postJson('/api/prices', array_merge([
            'product_id' => $productId,
            'region' => $lookups['area']->area_slug,
            'price' => 5,
            'store_name' => 'بقالة',
        ], $extra));
    }

    // ---------- منح النقاط عند إضافة سعر ----------

    public function test_adding_a_price_awards_points_once(): void
    {
        $this->addPriceRule(10);
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $this->makeUser());

        $this->storePrice($user, $lookups, $product->id)
            ->assertStatus(201)
            ->assertJsonPath('data.points_awarded', 10);

        $price = ProductPrice::firstOrFail();
        $tx = PointTransaction::firstOrFail();

        $this->assertSame(10, $user->refresh()->user_points_total);
        $this->assertSame('product_price', $tx->point_transaction_source_type);
        $this->assertSame($price->id, $tx->point_transaction_source_id);
    }

    public function test_no_rule_means_the_price_is_still_saved_with_zero_points(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $this->makeUser());

        $this->storePrice($user, $lookups, $product->id)
            ->assertStatus(201)
            ->assertJsonPath('data.points_awarded', 0);

        $this->assertSame(1, ProductPrice::count());
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_daily_limit_stops_points_but_not_the_price(): void
    {
        $this->addPriceRule(10, 1);
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $this->makeUser());

        $this->storePrice($user, $lookups, $product->id)->assertJsonPath('data.points_awarded', 10);
        $this->storePrice($user, $lookups, $product->id)->assertStatus(201)->assertJsonPath('data.points_awarded', 0);

        $this->assertSame(2, ProductPrice::count());
        $this->assertSame(10, $user->refresh()->user_points_total);
    }

    public function test_a_failed_validation_awards_nothing(): void
    {
        $this->addPriceRule(10);
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $this->makeUser());

        $this->storePrice($user, $lookups, $product->id, ['price' => -1])->assertStatus(400);

        $this->assertSame(0, PointTransaction::count());
        $this->assertSame(0, $user->refresh()->user_points_total);
    }

    public function test_if_awarding_fails_the_price_is_rolled_back(): void
    {
        $this->addPriceRule(10);
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $this->makeUser());

        $this->mock(PointService::class)
            ->shouldReceive('award')->andThrow(new \RuntimeException('boom'));

        $this->storePrice($user, $lookups, $product->id)->assertStatus(500);

        $this->assertSame(0, ProductPrice::count());
        $this->assertSame(0, PointTransaction::count());
    }

    // ---------- GET /api/me/points ----------

    public function test_points_requires_login(): void
    {
        $this->getJson('/api/me/points')->assertStatus(401);
    }

    public function test_points_shows_total_level_next_level_and_history(): void
    {
        $this->addPriceRule(10);
        $this->level('beginner', 0);
        $this->level('active', 100);
        $user = $this->makeUser();
        $user->forceFill(['user_points_total' => 40])->save();

        $rule = PointRule::firstOrFail();
        PointTransaction::create([
            'user_id' => $user->id, 'point_rule_id' => $rule->id, 'point_transaction_points' => 10,
            'point_transaction_source_type' => 'product_price', 'point_transaction_source_id' => 1,
        ]);

        $this->actingAs($user)->getJson('/api/me/points')
            ->assertOk()
            ->assertJsonPath('data.total', 40)
            ->assertJsonPath('data.level.key', 'beginner')
            ->assertJsonPath('data.next_level.key', 'active')
            ->assertJsonPath('data.next_level.points_needed', 60)
            ->assertJsonPath('history.total', 1)
            ->assertJsonPath('history.data.0.points', 10)
            ->assertJsonPath('history.data.0.rule.key', 'add_price');
    }

    public function test_points_works_with_no_levels_and_at_the_top_level(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->getJson('/api/me/points')
            ->assertOk()
            ->assertJsonPath('data.level', null)
            ->assertJsonPath('data.next_level', null)
            ->assertJsonPath('history.data', []);

        $this->level('beginner', 0);
        $user->forceFill(['user_points_total' => 5])->save();

        $this->actingAs($user)->getJson('/api/me/points')
            ->assertOk()
            ->assertJsonPath('data.level.key', 'beginner')
            ->assertJsonPath('data.next_level', null);
    }

    public function test_history_only_contains_my_transactions(): void
    {
        $rule = $this->addPriceRule();
        $me = $this->makeUser();
        $other = $this->makeUser();

        PointTransaction::create([
            'user_id' => $other->id, 'point_rule_id' => $rule->id, 'point_transaction_points' => 10,
            'point_transaction_source_type' => 'product_price', 'point_transaction_source_id' => 1,
        ]);

        $this->actingAs($me)->getJson('/api/me/points?user_id='.$other->id)
            ->assertOk()
            ->assertJsonPath('history.total', 0);
    }

    public function test_history_validation(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->getJson('/api/me/points?limit=51')->assertStatus(400);
    }

}