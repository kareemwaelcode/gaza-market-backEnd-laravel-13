<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardWorkspacePricing;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class WorkspacePricingTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/dashboard/workspace-pricing';

    private static int $counter = 0;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        $region = new Regions;
        $region->region_slug = 'r-'.Str::random(6);
        $region->region_name_en = 'Gaza';
        $region->region_name_ar = 'غزة';
        $region->save();

        $this->area = new Area;
        $this->area->area_slug = 'a-'.Str::random(6);
        $this->area->area_name_en = 'Al-Rimal';
        $this->area->area_name_ar = 'الرمال';
        $this->area->region_id = $region->id;
        $this->area->save();
    }

    private function owner(string $type = 'workspace', string $plan = 'free', string $status = Place::STATUS_APPROVED): User
    {
        $n = ++self::$counter;
        $user = UserFactory::new()->storeOwner()->create();

        $place = new Place;
        $place->fill([
            'place_name' => 'Place '.$n,
            'place_type' => $type,
            'place_address' => 'Street '.$n,
            'place_phone' => '056'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'place_whatsapp' => '970593'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'area_id' => $this->area->id,
            'store_category_id' => null,
        ]);
        $place->place_slug = 'p-'.Str::lower(Str::random(12));
        $place->place_status = $status;
        $place->place_plan = $plan;
        $place->user_id = $user->id;
        $place->save();

        return $user;
    }

    private function placeOf(User $user): Place
    {
        return Place::where('user_id', $user->id)->firstOrFail();
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'priceHour' => 5,
            'priceHalfDay' => 20,
            'priceFullDay' => 35.5,
            'priceWeek' => 150,
            'priceMonth' => 500,
            'openTime' => '08:00',
            'closeTime' => '20:00',
            'seatsTotal' => 100,
            'seatsAvailable' => 42,
        ];
    }

    private function emptyPayload(): array
    {
        return [
            'priceHour' => null,
            'priceHalfDay' => null,
            'priceFullDay' => null,
            'priceWeek' => null,
            'priceMonth' => null,
            'openTime' => null,
            'closeTime' => null,
            'seatsTotal' => null,
            'seatsAvailable' => null,
        ];
    }

    public function test_guest_gets_401_on_all_routes(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->putJson(self::URL, $this->payload())->assertStatus(401);
    }

    public function test_pending_owner_and_plain_user_get_403(): void
    {
        $this->actingAs($this->owner(status: Place::STATUS_PENDING))->getJson(self::URL)->assertStatus(403);

        $plain = $this->owner();
        $plain->user_role = 'user';
        $plain->save();
        $this->actingAs($plain)->putJson(self::URL, $this->payload())->assertStatus(403);
    }

    public function test_unsupported_place_types_get_404_on_all_routes(): void
    {
        foreach (['cafe', 'restaurant', 'restaurant-cafe', 'store'] as $type) {
            $user = $this->owner($type);

            $this->actingAs($user)->getJson(self::URL)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
            $this->actingAs($user)->putJson(self::URL, $this->payload())->assertStatus(404);
            $this->actingAs($user)->putJson(self::URL, [])->assertStatus(404);
        }

        $this->assertSame(0, DashboardWorkspacePricing::count());
    }

    public function test_free_and_paid_workspace_owners_both_have_access(): void
    {
        foreach (['free', 'paid'] as $plan) {
            $this->actingAs($this->owner('workspace', $plan))->putJson(self::URL, $this->payload())->assertOk();
        }

        $this->assertSame(2, DashboardWorkspacePricing::count());
    }

    public function test_show_returns_all_null_when_nothing_saved_yet(): void
    {
        $response = $this->actingAs($this->owner())->getJson(self::URL)->assertOk();

        $this->assertSame($this->emptyPayload(), $response->json('data'));
        $this->assertSame(0, DashboardWorkspacePricing::count());
    }

    public function test_update_creates_row_for_own_place_with_expected_shape(): void
    {
        $user = $this->owner();

        $data = $this->actingAs($user)->putJson(self::URL, $this->payload())->assertOk()->json('data');

        $this->assertEquals(5, $data['priceHour']);
        $this->assertEquals(20, $data['priceHalfDay']);
        $this->assertEquals(35.5, $data['priceFullDay']);
        $this->assertEquals(150, $data['priceWeek']);
        $this->assertEquals(500, $data['priceMonth']);
        $this->assertSame('08:00', $data['openTime']);
        $this->assertSame('20:00', $data['closeTime']);
        $this->assertSame(100, $data['seatsTotal']);
        $this->assertSame(42, $data['seatsAvailable']);

        $this->assertDatabaseHas('dashboard_workspace_pricing', [
            'place_id' => $this->placeOf($user)->id,
            'seats_total' => 100,
            'seats_available' => 42,
        ]);

        $this->actingAs($user)->getJson(self::URL)->assertOk()->assertJsonPath('data.openTime', '08:00');
    }

    public function test_update_replaces_existing_row_and_keeps_a_single_row(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->putJson(self::URL, $this->payload())->assertOk();
        $data = $this->actingAs($user)
            ->putJson(self::URL, $this->payload(['priceHour' => 7, 'priceWeek' => null, 'seatsAvailable' => 10]))
            ->assertOk()->json('data');

        $this->assertEquals(7, $data['priceHour']);
        $this->assertNull($data['priceWeek']);
        $this->assertSame(10, $data['seatsAvailable']);
        $this->assertSame(1, DashboardWorkspacePricing::count());
    }

    public function test_every_field_can_be_cleared_with_null_or_empty_string(): void
    {
        $user = $this->owner();
        $this->actingAs($user)->putJson(self::URL, $this->payload())->assertOk();

        $data = $this->actingAs($user)->putJson(self::URL, $this->emptyPayload())->assertOk()->json('data');
        $this->assertSame($this->emptyPayload(), $data);

        $this->actingAs($user)->putJson(self::URL, $this->payload())->assertOk();
        $blank = array_fill_keys(array_keys($this->emptyPayload()), '');
        $data = $this->actingAs($user)->putJson(self::URL, $blank)->assertOk()->json('data');
        $this->assertSame($this->emptyPayload(), $data);
    }

    public function test_zero_price_is_accepted(): void
    {
        $data = $this->actingAs($this->owner())
            ->putJson(self::URL, $this->payload(['priceHour' => 0]))
            ->assertOk()->json('data');

        $this->assertEquals(0, $data['priceHour']);
        $this->assertNotNull($data['priceHour']);
    }

    public function test_close_time_before_open_time_is_accepted(): void
    {
        $data = $this->actingAs($this->owner())
            ->putJson(self::URL, $this->payload(['openTime' => '18:00', 'closeTime' => '02:00']))
            ->assertOk()->json('data');

        $this->assertSame('18:00', $data['openTime']);
        $this->assertSame('02:00', $data['closeTime']);
    }

    public function test_seats_available_cannot_exceed_seats_total(): void
    {
        $user = $this->owner();

        $this->actingAs($user)
            ->putJson(self::URL, $this->payload(['seatsTotal' => 10, 'seatsAvailable' => 11]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'VALIDATION')
            ->assertJsonValidationErrorFor('seatsAvailable', 'errors');

        $this->actingAs($user)
            ->putJson(self::URL, $this->payload(['seatsTotal' => 10, 'seatsAvailable' => 10]))
            ->assertOk();

        $this->actingAs($user)
            ->putJson(self::URL, $this->payload(['seatsTotal' => null, 'seatsAvailable' => 5]))
            ->assertOk()->assertJsonPath('data.seatsAvailable', 5);
    }

    public function test_seats_total_must_be_at_least_one(): void
    {
        $this->actingAs($this->owner())
            ->putJson(self::URL, $this->payload(['seatsTotal' => 0, 'seatsAvailable' => 0]))
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('seatsTotal', 'errors');
    }

    public function test_missing_fields_are_rejected(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->putJson(self::URL, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');

        $partial = $this->payload();
        unset($partial['priceHour'], $partial['openTime']);

        $this->actingAs($user)->putJson(self::URL, $partial)
            ->assertStatus(422)
            ->assertJsonValidationErrorFor('priceHour', 'errors')
            ->assertJsonValidationErrorFor('openTime', 'errors');

        $this->assertSame(0, DashboardWorkspacePricing::count());
    }

    public function test_invalid_values_are_rejected_and_nothing_is_saved(): void
    {
        $user = $this->owner();

        $cases = [
            ['priceHour', -1],
            ['priceHour', 1000000],
            ['priceHour', 5.123],
            ['priceHour', 'abc'],
            ['priceMonth', [1]],
            ['openTime', '25:00'],
            ['openTime', '8:00'],
            ['closeTime', 'noon'],
            ['seatsTotal', 1.5],
            ['seatsTotal', 70000],
            ['seatsAvailable', -1],
            ['seatsAvailable', 'x'],
        ];

        foreach ($cases as [$field, $value]) {
            $this->actingAs($user)
                ->putJson(self::URL, $this->payload([$field => $value]))
                ->assertStatus(422)
                ->assertJsonValidationErrorFor($field, 'errors');
        }

        $this->assertSame(0, DashboardWorkspacePricing::count());
    }

    public function test_place_id_in_body_is_ignored(): void
    {
        $user = $this->owner();
        $other = $this->owner();

        $this->actingAs($user)
            ->putJson(self::URL, $this->payload(['place_id' => $this->placeOf($other)->id]))
            ->assertOk();

        $this->assertDatabaseHas('dashboard_workspace_pricing', ['place_id' => $this->placeOf($user)->id]);
        $this->assertDatabaseMissing('dashboard_workspace_pricing', ['place_id' => $this->placeOf($other)->id]);
    }

    public function test_owners_cannot_see_or_change_each_others_pricing(): void
    {
        $a = $this->owner();
        $b = $this->owner();

        $this->actingAs($a)->putJson(self::URL, $this->payload(['priceHour' => 9]))->assertOk();
        $this->actingAs($b)->putJson(self::URL, $this->payload(['priceHour' => 3]))->assertOk();

        $this->assertEquals(9, $this->actingAs($a)->getJson(self::URL)->json('data.priceHour'));
        $this->assertEquals(3, $this->actingAs($b)->getJson(self::URL)->json('data.priceHour'));
        $this->assertSame(2, DashboardWorkspacePricing::count());
    }

    public function test_database_allows_only_one_row_per_place(): void
    {
        $user = $this->owner();
        $placeId = $this->placeOf($user)->id;

        $first = new DashboardWorkspacePricing;
        $first->place_id = $placeId;
        $first->save();

        $this->expectException(UniqueConstraintViolationException::class);

        $second = new DashboardWorkspacePricing;
        $second->place_id = $placeId;
        $second->save();
    }
}