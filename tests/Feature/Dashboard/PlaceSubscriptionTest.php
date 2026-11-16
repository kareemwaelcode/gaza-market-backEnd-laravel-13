<?php

namespace Tests\Feature\Dashboard;

use App\Models\Place;
use App\Models\PlaceSubscription;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Dashboard\Concerns\MakesClinicPlaces;
use Tests\TestCase;

class PlaceSubscriptionTest extends TestCase
{
    use MakesClinicPlaces;
    use RefreshDatabase;

    private const URL = '/api/dashboard/subscription';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPlaces();
        $this->freezeNow();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_guest_gets_401_and_pending_owner_gets_403(): void
    {
        $this->getJson(self::URL)->assertStatus(401);

        $pending = $this->makeOwner('clinic', 'free', 'store', Place::STATUS_PENDING);
        $this->actingAs($pending)->getJson(self::URL)->assertStatus(403);
    }

    public function test_free_place_shows_free_without_dates(): void
    {
        $user = $this->makeOwner(null, 'free', 'cafe');

        $this->actingAs($user)->getJson(self::URL)
            ->assertOk()
            ->assertExactJson(['data' => ['plan' => 'free', 'expiresAt' => null, 'daysLeft' => null, 'price' => '99.00', 'currency' => 'ILS', 'periodMonths' => 2, 'current' => null]]);
    }

    public function test_grant_activates_for_two_months_from_the_moment_of_the_click(): void
    {
        $user = $this->makeOwner(null, 'free', 'cafe');
        $place = $this->placeOf($user);

        $subscription = PlaceSubscription::grantTo($place, null, 'دفع كاش');

        $this->assertSame('2026-11-16 08:00:00', $subscription->subscription_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2027-01-16 08:00:00', $subscription->subscription_ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('99.00', $subscription->subscription_amount);
        $this->assertSame('ILS', $subscription->subscription_currency);
        $this->assertSame(2, $subscription->subscription_period_months);

        $fresh = $this->placeOf($user);
        $this->assertSame('paid', $fresh->place_plan);
        $this->assertSame('2027-01-16 08:00:00', $fresh->place_plan_expires_at->format('Y-m-d H:i:s'));

        $this->actingAs($user)->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.plan', 'paid')
            ->assertJsonPath('data.expiresAt', '2027-01-16T08:00:00.000Z')
            ->assertJsonPath('data.daysLeft', 61)
            ->assertJsonPath('data.current.amount', '99.00')
            ->assertJsonPath('data.current.currency', 'ILS')
            ->assertJsonPath('data.current.periodMonths', 2);
    }

    public function test_response_carries_the_renewal_price_and_period_from_config(): void
    {
        config(['subscription.price' => 120, 'subscription.currency' => 'USD', 'subscription.period_months' => 3]);

        $user = $this->makeOwner(null, 'free', 'cafe');

        $this->actingAs($user)->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.price', '120.00')
            ->assertJsonPath('data.currency', 'USD')
            ->assertJsonPath('data.periodMonths', 3);
    }

    public function test_early_renewal_starts_from_the_current_end_and_keeps_remaining_days(): void
    {
        $user = $this->makeOwner(null, 'free', 'cafe');
        $place = $this->placeOf($user);

        PlaceSubscription::grantTo($place);
        Carbon::setTestNow(Carbon::parse('2026-12-16 08:00:00', 'UTC'));

        $second = PlaceSubscription::grantTo($this->placeOf($user));

        $this->assertSame('2027-01-16 08:00:00', $second->subscription_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2027-03-16 08:00:00', $second->subscription_ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('2027-03-16 08:00:00', $this->placeOf($user)->place_plan_expires_at->format('Y-m-d H:i:s'));
        $this->assertSame(2, PlaceSubscription::where('place_id', $place->id)->count());
    }

    public function test_renewal_after_expiry_starts_from_now(): void
    {
        $user = $this->makeOwner(null, 'free', 'cafe');
        PlaceSubscription::grantTo($this->placeOf($user));

        Carbon::setTestNow(Carbon::parse('2027-02-01 10:00:00', 'UTC'));

        $second = PlaceSubscription::grantTo($this->placeOf($user));

        $this->assertSame('2027-02-01 10:00:00', $second->subscription_starts_at->format('Y-m-d H:i:s'));
        $this->assertSame('2027-04-01 10:00:00', $second->subscription_ends_at->format('Y-m-d H:i:s'));
        $this->assertSame('paid', $this->placeOf($user)->place_plan);
    }

    public function test_plan_returns_to_free_the_moment_it_expires_even_if_the_row_still_says_paid(): void
    {
        $user = $this->makeOwner(null, 'free', 'cafe');
        PlaceSubscription::grantTo($this->placeOf($user));

        Carbon::setTestNow(Carbon::parse('2027-01-16 08:00:01', 'UTC'));

        $place = $this->placeOf($user);
        $this->assertSame('paid', $place->getRawOriginal('place_plan'));
        $this->assertSame('free', $place->place_plan);

        $this->actingAs($user)->getJson(self::URL)
            ->assertOk()
            ->assertExactJson(['data' => ['plan' => 'free', 'expiresAt' => null, 'daysLeft' => null, 'price' => '99.00', 'currency' => 'ILS', 'periodMonths' => 2, 'current' => null]]);
    }

    public function test_paid_without_expiry_stays_paid(): void
    {
        $user = $this->makeOwner(null, 'paid', 'cafe');

        $this->assertSame('paid', $this->placeOf($user)->place_plan);

        $this->actingAs($user)->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.plan', 'paid')
            ->assertJsonPath('data.expiresAt', null)
            ->assertJsonPath('data.daysLeft', null);
    }

    public function test_changing_the_price_later_does_not_touch_old_payments(): void
    {
        $user = $this->makeOwner(null, 'free', 'cafe');
        $first = PlaceSubscription::grantTo($this->placeOf($user));

        config(['subscription.price' => 120]);
        $second = PlaceSubscription::grantTo($this->placeOf($user));

        $this->assertSame('99.00', $first->fresh()->subscription_amount);
        $this->assertSame('120.00', $second->subscription_amount);
    }

    public function test_cancel_returns_to_free_keeps_the_history_and_only_touches_that_place(): void
    {
        $mine = $this->makeOwner(null, 'free', 'cafe');
        $other = $this->makeOwner(null, 'free', 'cafe');

        PlaceSubscription::grantTo($this->placeOf($mine));
        PlaceSubscription::grantTo($this->placeOf($mine));
        PlaceSubscription::grantTo($this->placeOf($other));

        $cancelled = PlaceSubscription::cancelFor($this->placeOf($mine));

        $this->assertSame(2, $cancelled);
        $place = $this->placeOf($mine);
        $this->assertSame('free', $place->place_plan);
        $this->assertNull($place->place_plan_expires_at);
        $this->assertSame(2, PlaceSubscription::where('place_id', $place->id)->whereNotNull('subscription_cancelled_at')->count());

        $this->assertSame('paid', $this->placeOf($other)->place_plan);
        $this->assertSame(0, PlaceSubscription::where('place_id', $this->placeOf($other)->id)->whereNotNull('subscription_cancelled_at')->count());

        $this->actingAs($mine)->getJson(self::URL)->assertOk()->assertJsonPath('data.plan', 'free');
    }

    public function test_show_returns_only_the_owners_own_subscription(): void
    {
        $mine = $this->makeOwner(null, 'free', 'cafe');
        $other = $this->makeOwner(null, 'free', 'cafe');

        PlaceSubscription::grantTo($this->placeOf($other));

        $this->actingAs($mine)->getJson(self::URL)
            ->assertOk()
            ->assertJsonPath('data.plan', 'free')
            ->assertJsonPath('data.current', null);
    }

    public function test_a_place_with_subscriptions_cannot_be_deleted(): void
    {
        $user = $this->makeOwner(null, 'free', 'cafe');
        PlaceSubscription::grantTo($this->placeOf($user));

        $this->expectException(QueryException::class);

        $this->placeOf($user)->delete();
    }
}