<?php

namespace Tests\Feature\Dashboard;

use App\Models\DashboardClinicHour;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Dashboard\Concerns\MakesClinicPlaces;
use Tests\TestCase;

class ClinicHoursTest extends TestCase
{
    use MakesClinicPlaces;
    use RefreshDatabase;

    private const URL = '/api/dashboard/clinic-hours';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPlaces();
    }

    /** @return array<string, mixed> */
    private function week(int $slot = 30, array $override = []): array
    {
        $days = [];

        foreach (DashboardClinicHour::DAYS as $key) {
            $days[] = ['key' => $key, 'open' => $key !== 'fri', 'from' => '08:00', 'to' => '14:00'];
        }

        return array_merge(['slot' => $slot, 'days' => $days], $override);
    }

    public function test_guest_gets_401_and_pending_owner_gets_403(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->putJson(self::URL, $this->week())->assertStatus(401);

        $this->actingAs($this->makeOwner(status: Place::STATUS_PENDING))->getJson(self::URL)->assertStatus(403);
    }

    public function test_non_clinic_places_get_404(): void
    {
        foreach ([$this->makeOwner('pharmacy'), $this->makeOwner(null), $this->makeOwner(null, type: 'cafe')] as $owner) {
            $this->actingAs($owner)->getJson(self::URL)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
            $this->actingAs($owner)->putJson(self::URL, $this->week())->assertStatus(404);
        }

        $this->assertSame(0, DashboardClinicHour::count());
    }

    public function test_show_returns_frontend_defaults_when_nothing_is_saved(): void
    {
        $response = $this->actingAs($this->makeOwner())->getJson(self::URL)->assertOk();

        $response->assertJsonPath('data.slot', 30);
        $this->assertSame(DashboardClinicHour::DAYS, array_column($response->json('data.days'), 'key'));

        foreach ($response->json('data.days') as $day) {
            $this->assertSame($day['key'] !== 'fri', $day['open']);
            $this->assertSame('09:00', $day['from']);
            $this->assertSame('17:00', $day['to']);
        }

        $this->assertSame(0, DashboardClinicHour::count());
    }

    public function test_put_saves_all_seven_days_with_one_slot_and_show_returns_them(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->putJson(self::URL, $this->week(45))->assertOk()
            ->assertJsonPath('data.slot', 45)
            ->assertJsonPath('data.days.0.key', 'sat')
            ->assertJsonPath('data.days.0.from', '08:00')
            ->assertJsonPath('data.days.0.to', '14:00')
            ->assertJsonPath('data.days.6.key', 'fri')
            ->assertJsonPath('data.days.6.open', false);

        $placeId = $this->placeOf($owner)->id;
        $this->assertSame(7, DashboardClinicHour::where('place_id', $placeId)->count());
        $this->assertSame([45], DashboardClinicHour::where('place_id', $placeId)->pluck('slot_minutes')->unique()->values()->all());

        $this->actingAs($owner)->getJson(self::URL)->assertOk()->assertJsonPath('data.slot', 45);
    }

    public function test_put_again_updates_in_place_without_duplicates(): void
    {
        $owner = $this->makeOwner();

        $this->actingAs($owner)->putJson(self::URL, $this->week(30))->assertOk();
        $changed = $this->week(60);
        $changed['days'][0] = ['key' => 'sat', 'open' => false, 'from' => '10:00', 'to' => '12:00'];
        $this->actingAs($owner)->putJson(self::URL, $changed)->assertOk()
            ->assertJsonPath('data.slot', 60)
            ->assertJsonPath('data.days.0.open', false)
            ->assertJsonPath('data.days.0.from', '10:00');

        $this->assertSame(7, DashboardClinicHour::count());
    }

    public function test_a_closed_day_may_omit_times(): void
    {
        $owner = $this->makeOwner();
        $week = $this->week();
        $week['days'][6] = ['key' => 'fri', 'open' => false];

        $this->actingAs($owner)->putJson(self::URL, $week)->assertOk()
            ->assertJsonPath('data.days.6.open', false)
            ->assertJsonPath('data.days.6.from', '09:00')
            ->assertJsonPath('data.days.6.to', '17:00');
    }

    public function test_put_validates_input(): void
    {
        $owner = $this->makeOwner();

        $bad = [
            [],
            $this->week(25),
            $this->week(30, ['slot' => 'abc']),
            $this->week(30, ['days' => array_slice($this->week()['days'], 0, 6)]),
            $this->week(30, ['days' => array_merge($this->week()['days'], [['key' => 'sat', 'open' => true, 'from' => '08:00', 'to' => '09:00']])]),
        ];

        $dup = $this->week();
        $dup['days'][1]['key'] = 'sat';
        $bad[] = $dup;

        $badKey = $this->week();
        $badKey['days'][0]['key'] = 'xyz';
        $bad[] = $badKey;

        $reversed = $this->week();
        $reversed['days'][0]['from'] = '14:00';
        $reversed['days'][0]['to'] = '08:00';
        $bad[] = $reversed;

        $equal = $this->week();
        $equal['days'][0]['to'] = $equal['days'][0]['from'];
        $bad[] = $equal;

        $missing = $this->week();
        unset($missing['days'][0]['from']);
        $bad[] = $missing;

        $format = $this->week();
        $format['days'][0]['from'] = '8am';
        $bad[] = $format;

        $notBool = $this->week();
        $notBool['days'][0]['open'] = 'maybe';
        $bad[] = $notBool;

        foreach ($bad as $payload) {
            $this->actingAs($owner)->putJson(self::URL, $payload)->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        }

        $this->assertSame(0, DashboardClinicHour::count());
    }

    public function test_hours_are_isolated_per_clinic(): void
    {
        $mine = $this->makeOwner();
        $other = $this->makeOwner();

        $this->actingAs($other)->putJson(self::URL, $this->week(60))->assertOk();
        $this->actingAs($mine)->putJson(self::URL, $this->week(15))->assertOk();

        $this->assertSame(60, DashboardClinicHour::where('place_id', $this->placeOf($other)->id)->first()->slot_minutes);
        $this->actingAs($other)->getJson(self::URL)->assertJsonPath('data.slot', 60);
        $this->assertSame(14, DashboardClinicHour::count());
    }

    public function test_deleting_a_place_deletes_its_hours(): void
    {
        $owner = $this->makeOwner();
        $this->actingAs($owner)->putJson(self::URL, $this->week())->assertOk();

        $this->placeOf($owner)->delete();

        $this->assertSame(0, DashboardClinicHour::count());
    }
}