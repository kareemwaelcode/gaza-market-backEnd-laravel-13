<?php

namespace Tests\Feature\Dashboard;

use App\Models\DashboardNotification;
use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Feature\Dashboard\Concerns\MakesClinicPlaces;
use Tests\TestCase;

class NotificationTest extends TestCase
{
    use MakesClinicPlaces;
    use RefreshDatabase;

    private const URL = '/api/dashboard/notifications';

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpPlaces();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function note(int $placeId, string $title = 'إشعار', bool $read = false, ?Carbon $at = null): DashboardNotification
    {
        $n = new DashboardNotification(['icon' => 'bell', 'title' => $title, 'sub' => null]);
        $n->place_id = $placeId;
        $n->is_read = $read;

        if ($at !== null) {
            $n->created_at = $at;
        }

        $n->save();

        return $n;
    }

    public function test_guest_gets_401_and_pending_owner_gets_403(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->postJson(self::URL.'/read')->assertStatus(401);
        $this->deleteJson(self::URL)->assertStatus(401);

        $pending = $this->makeOwner('clinic', 'free', 'store', Place::STATUS_PENDING);

        $this->actingAs($pending)->getJson(self::URL)->assertStatus(403);
        $this->actingAs($pending)->postJson(self::URL.'/read')->assertStatus(403);
        $this->actingAs($pending)->deleteJson(self::URL)->assertStatus(403);
    }

    public function test_every_place_type_has_access(): void
    {
        foreach (['workspace', 'cafe', 'restaurant', 'restaurant-cafe', 'store'] as $type) {
            $this->actingAs($this->makeOwner(null, 'free', $type))->getJson(self::URL)->assertOk();
        }
    }

    public function test_index_returns_newest_first_with_expected_shape_and_unread_count(): void
    {
        $this->freezeNow();
        $user = $this->makeOwner(null, 'free', 'cafe');
        $placeId = $this->placeOf($user)->id;

        $old = $this->note($placeId, 'قديم', true, Carbon::now()->subHour());
        $new = $this->note($placeId, 'جديد', false, Carbon::now());

        $response = $this->actingAs($user)->getJson(self::URL)->assertOk();

        $this->assertSame([(string) $new->id, (string) $old->id], array_column($response->json('data'), 'id'));
        $this->assertSame(1, $response->json('meta.unread'));
        $this->assertSame([
            'id' => (string) $new->id,
            'icon' => 'bell',
            'title' => 'جديد',
            'sub' => '',
            'isRead' => false,
            'createdAt' => '2026-11-16T08:00:00.000Z',
        ], $response->json('data.0'));
    }

    public function test_index_never_returns_another_places_notifications(): void
    {
        $mine = $this->makeOwner(null, 'free', 'cafe');
        $other = $this->makeOwner(null, 'free', 'cafe');

        $this->note($this->placeOf($other)->id, 'للغير');

        $this->actingAs($mine)->getJson(self::URL)
            ->assertOk()
            ->assertJsonCount(0, 'data')
            ->assertJsonPath('meta.unread', 0);
    }

    public function test_mark_all_read_only_touches_own_place(): void
    {
        $mine = $this->makeOwner(null, 'free', 'cafe');
        $other = $this->makeOwner(null, 'free', 'cafe');

        $this->note($this->placeOf($mine)->id);
        $this->note($this->placeOf($mine)->id);
        $this->note($this->placeOf($other)->id);

        $this->actingAs($mine)->postJson(self::URL.'/read')->assertNoContent();

        $this->assertSame(0, DashboardNotification::where('place_id', $this->placeOf($mine)->id)->where('is_read', false)->count());
        $this->assertSame(1, DashboardNotification::where('place_id', $this->placeOf($other)->id)->where('is_read', false)->count());
    }

    public function test_clear_hard_deletes_only_own_notifications(): void
    {
        $mine = $this->makeOwner(null, 'free', 'cafe');
        $other = $this->makeOwner(null, 'free', 'cafe');

        $this->note($this->placeOf($mine)->id);
        $this->note($this->placeOf($mine)->id, read: true);
        $this->note($this->placeOf($other)->id);

        $this->actingAs($mine)->deleteJson(self::URL)->assertNoContent();

        $this->assertDatabaseCount('dashboard_notifications', 1);
        $this->assertDatabaseHas('dashboard_notifications', ['place_id' => $this->placeOf($other)->id]);
    }

    public function test_record_keeps_only_the_latest_thirty_per_place(): void
    {
        $this->freezeNow();
        $user = $this->makeOwner(null, 'free', 'cafe');
        $other = $this->makeOwner(null, 'free', 'cafe');
        $placeId = $this->placeOf($user)->id;

        $this->note($this->placeOf($other)->id, 'للغير');

        for ($i = 1; $i <= 32; $i++) {
            DashboardNotification::record($placeId, 'bell', 'إشعار '.$i, 'تفصيل');
        }

        $titles = DashboardNotification::where('place_id', $placeId)->orderBy('id')->pluck('title')->all();

        $this->assertCount(DashboardNotification::MAX_PER_PLACE, $titles);
        $this->assertSame('إشعار 3', $titles[0]);
        $this->assertSame('إشعار 32', end($titles));
        $this->assertSame(1, DashboardNotification::where('place_id', $this->placeOf($other)->id)->count());

        $response = $this->actingAs($user)->getJson(self::URL)->assertOk();
        $this->assertCount(30, $response->json('data'));
        $this->assertSame('إشعار 32', $response->json('data.0.title'));
    }

    public function test_deleting_the_place_deletes_its_notifications(): void
    {
        $user = $this->makeOwner(null, 'free', 'cafe');
        $this->note($this->placeOf($user)->id);

        $this->placeOf($user)->delete();

        $this->assertDatabaseCount('dashboard_notifications', 0);
    }
}