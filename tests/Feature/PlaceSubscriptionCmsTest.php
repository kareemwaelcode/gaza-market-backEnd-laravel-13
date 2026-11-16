<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Place;
use App\Models\PlaceSubscription;
use App\Models\Regions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlaceSubscriptionCmsTest extends TestCase
{
    use RefreshDatabase;

    private static int $counter = 0;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-11-16 10:00:00');

        $region = new Regions;
        $region->region_slug = 'r-'.Str::random(6);
        $region->region_name_en = 'Gaza';
        $region->region_name_ar = 'غزة';
        $region->save();

        $this->area = new Area;
        $this->area->area_slug = 'Alrimal';
        $this->area->area_name_en = 'Al-Rimal';
        $this->area->area_name_ar = 'الرمال';
        $this->area->region_id = $region->id;
        $this->area->save();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function makePlace(string $status = Place::STATUS_APPROVED, ?string $plan = null, ?Carbon $expiresAt = null): Place
    {
        $n = ++self::$counter;

        $user = new User;
        $user->user_phone = '059'.str_pad((string) $n, 7, '0', STR_PAD_LEFT);
        $user->user_full_name = 'Owner '.$n;
        $user->save();

        $place = new Place;
        $place->fill([
            'place_name' => 'Place '.$n,
            'place_type' => 'cafe',
            'place_address' => 'Street',
            'place_phone' => '056'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'place_whatsapp' => '970593'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'area_id' => $this->area->id,
        ]);
        $place->place_slug = 'p-'.Str::lower(Str::random(12));
        $place->place_status = $status;
        $place->user_id = $user->id;
        if ($status === Place::STATUS_APPROVED) {
            $place->place_approved_at = now();
        }
        if ($plan !== null) {
            $place->forceFill(['place_plan' => $plan, 'place_plan_expires_at' => $expiresAt]);
        }
        $place->save();

        return $place;
    }

    private function subscribe(Place $place, array $data = [])
    {
        return $this->postJson("/cms/admins/place_subscribe/{$place->id}", $data);
    }

    // ---------------------------------------------------------------- التفعيل

    public function test_subscribe_activates_a_free_approved_place(): void
    {
        $place = $this->makePlace();

        $this->subscribe($place)
            ->assertOk()
            ->assertJsonPath('redirect', route('place.show', $place->id));

        $fresh = $place->fresh();
        $this->assertSame('paid', $fresh->place_plan);
        $this->assertTrue($fresh->place_plan_expires_at->equalTo(now()->addMonthsNoOverflow(2)));

        $row = PlaceSubscription::where('place_id', $place->id)->sole();
        $this->assertSame('99.00', $row->subscription_amount);
        $this->assertSame('ILS', $row->subscription_currency);
        $this->assertSame(2, $row->subscription_period_months);
        $this->assertNull($row->subscription_activated_by);
        $this->assertNull($row->subscription_cancelled_at);
    }

    public function test_subscribe_stores_the_optional_notes(): void
    {
        $place = $this->makePlace();

        $this->subscribe($place, ['notes' => 'Paid in cash'])->assertOk();

        $this->assertSame('Paid in cash', PlaceSubscription::where('place_id', $place->id)->sole()->subscription_notes);
    }

    public function test_notes_longer_than_255_characters_are_refused(): void
    {
        $place = $this->makePlace();

        $this->subscribe($place, ['notes' => str_repeat('a', 256)])->assertStatus(422);

        $this->assertSame(0, PlaceSubscription::count());
        $this->assertSame('free', $place->fresh()->place_plan);
    }

    public function test_only_approved_places_can_be_subscribed(): void
    {
        foreach ([Place::STATUS_PENDING, Place::STATUS_REJECTED] as $status) {
            $place = $this->makePlace($status);

            $this->subscribe($place)->assertStatus(409);

            $this->assertSame('free', $place->fresh()->place_plan);
        }

        $this->assertSame(0, PlaceSubscription::count());
    }

    public function test_subscribe_unknown_place_is_404(): void
    {
        $this->postJson('/cms/admins/place_subscribe/999999')->assertNotFound();
    }

    public function test_early_renewal_starts_from_the_end_of_the_current_plan(): void
    {
        $place = $this->makePlace();

        $this->subscribe($place)->assertOk();
        $this->subscribe($place)->assertOk();

        $this->assertSame(2, PlaceSubscription::where('place_id', $place->id)->count());
        $this->assertTrue(
            $place->fresh()->place_plan_expires_at->equalTo(now()->addMonthsNoOverflow(2)->addMonthsNoOverflow(2))
        );
    }

    public function test_renewal_after_expiry_starts_from_now(): void
    {
        $place = $this->makePlace(Place::STATUS_APPROVED, 'paid', now()->subDays(3));

        $this->subscribe($place)->assertOk();

        $fresh = $place->fresh();
        $this->assertSame('paid', $fresh->place_plan);
        $this->assertTrue($fresh->place_plan_expires_at->equalTo(now()->addMonthsNoOverflow(2)));
    }

    // ---------------------------------------------------------------- الإلغاء

    public function test_cancel_returns_the_place_to_free_and_keeps_the_record(): void
    {
        $place = $this->makePlace();
        $this->subscribe($place)->assertOk();

        $this->postJson("/cms/admins/place_cancel_subscription/{$place->id}")
            ->assertOk()
            ->assertJsonPath('redirect', route('place.show', $place->id));

        $fresh = $place->fresh();
        $this->assertSame('free', $fresh->place_plan);
        $this->assertNull($fresh->place_plan_expires_at);

        $row = PlaceSubscription::where('place_id', $place->id)->sole();
        $this->assertNotNull($row->subscription_cancelled_at);
    }

    public function test_cancel_is_refused_when_there_is_no_active_subscription(): void
    {
        $free = $this->makePlace();
        $expired = $this->makePlace(Place::STATUS_APPROVED, 'paid', now()->subDay());

        $this->postJson("/cms/admins/place_cancel_subscription/{$free->id}")->assertStatus(409);
        $this->postJson("/cms/admins/place_cancel_subscription/{$expired->id}")->assertStatus(409);
    }

    public function test_cancel_unknown_place_is_404(): void
    {
        $this->postJson('/cms/admins/place_cancel_subscription/999999')->assertNotFound();
    }

    // ---------------------------------------------------------------- الفلتر

    private function idsFor(array $query): array
    {
        $ids = $this->get(route('place.index', $query))->viewData('places')->pluck('id')->all();
        sort($ids);

        return $ids;
    }

    public function test_plan_filter_splits_paid_free_and_expiring(): void
    {
        $free = $this->makePlace();
        $paidFar = $this->makePlace(Place::STATUS_APPROVED, 'paid', now()->addDays(30));
        $paidSoon = $this->makePlace(Place::STATUS_APPROVED, 'paid', now()->addDays(3));
        $expired = $this->makePlace(Place::STATUS_APPROVED, 'paid', now()->subDay());
        $legacyPaid = $this->makePlace(Place::STATUS_APPROVED, 'paid', null);

        $this->assertSame(
            collect([$paidFar, $paidSoon, $legacyPaid])->pluck('id')->sort()->values()->all(),
            $this->idsFor(['plan' => 'paid'])
        );
        $this->assertSame(
            collect([$free, $expired])->pluck('id')->sort()->values()->all(),
            $this->idsFor(['plan' => 'free'])
        );
        $this->assertSame([$paidSoon->id], $this->idsFor(['plan' => 'expiring']));
    }

    public function test_expiring_filter_covers_only_the_last_three_days(): void
    {
        $inside = $this->makePlace(Place::STATUS_APPROVED, 'paid', now()->addDays(3)->subMinute());
        $outside = $this->makePlace(Place::STATUS_APPROVED, 'paid', now()->addDays(3)->addHour());
        $this->makePlace(Place::STATUS_APPROVED, 'paid', now()->addDays(7));

        $this->assertSame([$inside->id], $this->idsFor(['plan' => 'expiring']));
        $this->assertNotContains($outside->id, $this->idsFor(['plan' => 'expiring']));
    }

    public function test_expiring_filter_is_ordered_by_the_nearest_end_first(): void
    {
        $later = $this->makePlace(Place::STATUS_APPROVED, 'paid', now()->addDays(2));
        $sooner = $this->makePlace(Place::STATUS_APPROVED, 'paid', now()->addDays(1));

        $ids = $this->get(route('place.index', ['plan' => 'expiring']))->viewData('places')->pluck('id')->all();

        $this->assertSame([$sooner->id, $later->id], $ids);
    }

    public function test_invalid_plan_filter_is_ignored_with_a_notice(): void
    {
        $a = $this->makePlace();
        $b = $this->makePlace(Place::STATUS_APPROVED, 'paid', now()->addDays(30));

        $response = $this->get(route('place.index', ['plan' => 'bogus']));

        $response->assertOk();
        $this->assertSame(['plan'], $response->viewData('ignoredFilters'));
        $this->assertSame([$a->id, $b->id], $this->idsFor(['plan' => 'bogus']));
    }

    public function test_invalid_plan_filter_is_422_for_json_requests(): void
    {
        $this->getJson(route('place.index', ['plan' => 'bogus']))->assertStatus(422);
    }

    // ---------------------------------------------------------------- الصفحات

    public function test_show_page_offers_the_right_buttons(): void
    {
        $free = $this->makePlace();
        $pending = $this->makePlace(Place::STATUS_PENDING);
        $paid = $this->makePlace();
        $this->subscribe($paid)->assertOk();

        $this->get(route('place.show', $free->id))
            ->assertOk()
            ->assertSee('Activate subscription')
            ->assertDontSee('Cancel subscription')
            ->assertSee('No payments yet.');

        $this->get(route('place.show', $pending->id))
            ->assertOk()
            ->assertDontSee('Activate subscription')
            ->assertDontSee('Renew subscription');

        $this->get(route('place.show', $paid->id))
            ->assertOk()
            ->assertSee('Renew subscription')
            ->assertSee('Cancel subscription')
            ->assertSee('99.00 ILS');
    }

    public function test_dates_are_displayed_in_the_display_timezone_while_stored_in_utc(): void
    {
        $place = $this->makePlace();
        $this->subscribe($place)->assertOk();

        // now = 2026-11-16 10:00 UTC، فالنهاية 2027-01-16 10:00 UTC = 12:00 بتوقيت غزة (UTC+2).
        $stored = PlaceSubscription::where('place_id', $place->id)->sole();
        $this->assertSame('2027-01-16 10:00:00', $stored->getRawOriginal('subscription_ends_at'));

        $this->get(route('place.show', $place->id))
            ->assertOk()
            ->assertSee('2027-01-16 12:00')
            ->assertDontSee('2027-01-16 10:00');

        $this->get(route('place.index'))
            ->assertOk()
            ->assertSee('until 2027-01-16')
            ->assertSee('2026-11-16 12:00');
    }

    // ---------------------------------------------------------------- الحذف

    public function test_place_with_payment_records_cannot_be_deleted(): void
    {
        $place = $this->makePlace();
        $this->subscribe($place)->assertOk();

        $this->deleteJson("/cms/admins/place/{$place->id}")
            ->assertStatus(409)
            ->assertJson(['icon' => 'error']);

        $this->assertNotNull(Place::find($place->id));
    }

    public function test_place_without_payment_records_can_be_deleted(): void
    {
        $place = $this->makePlace();

        $this->deleteJson("/cms/admins/place/{$place->id}")->assertOk();

        $this->assertNull(Place::find($place->id));
    }
}