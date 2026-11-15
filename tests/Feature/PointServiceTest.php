<?php

namespace Tests\Feature;

use App\Models\PointRule;
use App\Models\PointTransaction;
use App\Services\PointService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class PointServiceTest extends TestCase
{
    use BuildsProductPrices;
    use RefreshDatabase;

    private function rule(string $key = 'add_price', int $points = 10, ?int $limit = null, bool $active = true): PointRule
    {
        return PointRule::create([
            'point_rule_key' => $key,
            'point_rule_name_en' => 'Rule '.$key,
            'point_rule_name_ar' => 'قاعدة',
            'point_rule_points' => $points,
            'point_rule_daily_limit' => $limit,
            'point_rule_is_active' => $active,
        ]);
    }

    private function service(): PointService
    {
        return app(PointService::class);
    }

    public function test_it_awards_points_and_updates_the_total(): void
    {
        $this->rule(points: 10);
        $user = $this->makeUser();

        $tx = $this->service()->award($user->id, 'add_price', 'product_price', 1);

        $this->assertNotNull($tx);
        $this->assertSame(10, $tx->point_transaction_points);
        $this->assertSame(10, $user->refresh()->user_points_total);
        $this->assertSame(1, PointTransaction::count());
    }

    public function test_the_same_source_is_never_awarded_twice(): void
    {
        $this->rule();
        $user = $this->makeUser();

        $this->assertNotNull($this->service()->award($user->id, 'add_price', 'product_price', 7));
        $this->assertNull($this->service()->award($user->id, 'add_price', 'product_price', 7));

        $this->assertSame(10, $user->refresh()->user_points_total);
        $this->assertSame(1, PointTransaction::count());
    }

    public function test_a_different_source_is_awarded_again(): void
    {
        $this->rule();
        $user = $this->makeUser();

        $this->service()->award($user->id, 'add_price', 'product_price', 1);
        $this->service()->award($user->id, 'add_price', 'product_price', 2);

        $this->assertSame(20, $user->refresh()->user_points_total);
    }

    public function test_unknown_or_inactive_rules_award_nothing(): void
    {
        $this->rule('off', 10, null, false);
        $user = $this->makeUser();

        $this->assertNull($this->service()->award($user->id, 'missing', 'product_price', 1));
        $this->assertNull($this->service()->award($user->id, 'off', 'product_price', 2));

        $this->assertSame(0, PointTransaction::count());
        $this->assertSame(0, $user->refresh()->user_points_total);
    }

    public function test_a_zero_point_rule_awards_nothing(): void
    {
        $this->rule(points: 0);

        $this->assertNull($this->service()->award($this->makeUser()->id, 'add_price', 'product_price', 1));
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_the_daily_limit_is_enforced_per_user_and_rule(): void
    {
        $this->rule(limit: 2);
        $user = $this->makeUser();
        $other = $this->makeUser();

        $this->assertNotNull($this->service()->award($user->id, 'add_price', 'product_price', 1));
        $this->assertNotNull($this->service()->award($user->id, 'add_price', 'product_price', 2));
        $this->assertNull($this->service()->award($user->id, 'add_price', 'product_price', 3));

        $this->assertSame(20, $user->refresh()->user_points_total);
        // مستخدم تاني ما بيتأثر بحد غيره
        $this->assertNotNull($this->service()->award($other->id, 'add_price', 'product_price', 4));
    }

    public function test_the_daily_limit_resets_at_midnight_gaza_time(): void
    {
        config(['points.timezone' => 'Asia/Gaza']);
        $this->rule(limit: 1);
        $user = $this->makeUser();

        // 23:30 بتوقيت غزة (UTC+2 بالشتاء) = 21:30 UTC
        $this->travelTo(now('Asia/Gaza')->setDate(2026, 12, 10)->setTime(23, 30));
        $this->assertNotNull($this->service()->award($user->id, 'add_price', 'product_price', 1));
        $this->assertNull($this->service()->award($user->id, 'add_price', 'product_price', 2));

        // بعد منتصف الليل بتوقيت غزة = يوم جديد (لسا نفس اليوم بتوقيت UTC)
        $this->travelTo(now('Asia/Gaza')->setDate(2026, 12, 11)->setTime(0, 30));
        $this->assertNotNull($this->service()->award($user->id, 'add_price', 'product_price', 3));
    }

    public function test_negative_points_never_push_the_total_below_zero(): void
    {
        $this->rule('penalty', -50);
        $user = $this->makeUser();
        $user->forceFill(['user_points_total' => 30])->save();

        $tx = $this->service()->award($user->id, 'penalty', 'product_price', 1);

        $this->assertSame(-50, $tx->point_transaction_points);
        $this->assertSame(0, $user->refresh()->user_points_total);
    }

    public function test_suspended_or_missing_users_get_nothing(): void
    {
        $this->rule();
        $user = $this->makeUser();
        $user->forceFill(['user_status' => 'suspended'])->save();

        $this->assertNull($this->service()->award($user->id, 'add_price', 'product_price', 1));
        $this->assertNull($this->service()->award(999999, 'add_price', 'product_price', 2));
        $this->assertSame(0, PointTransaction::count());
    }

    public function test_the_snapshot_keeps_the_points_even_if_the_rule_changes_later(): void
    {
        $rule = $this->rule(points: 10);
        $user = $this->makeUser();

        $this->service()->award($user->id, 'add_price', 'product_price', 1);
        $rule->update(['point_rule_points' => 99]);

        $this->assertSame(10, PointTransaction::firstOrFail()->point_transaction_points);
    }
}