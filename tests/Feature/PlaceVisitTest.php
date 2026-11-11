<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Place;
use App\Models\PlaceVisit;
use App\Models\Regions;
use App\Models\User;
use Carbon\Carbon;
use Database\Factories\UserFactory;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class PlaceVisitTest extends TestCase
{
    use RefreshDatabase;

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
        $this->area->area_slug = 'Alrimal';
        $this->area->area_name_en = 'Al-Rimal';
        $this->area->area_name_ar = 'الرمال';
        $this->area->region_id = $region->id;
        $this->area->save();
    }

    private function makePlace(string $status = Place::STATUS_APPROVED): Place
    {
        $n = ++self::$counter;
        $owner = UserFactory::new()->storeOwner()->create();

        $place = new Place;
        $place->fill([
            'place_name' => 'Place '.$n,
            'place_type' => 'cafe',
            'place_address' => 'Street '.$n,
            'place_phone' => '056'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'place_whatsapp' => '970593'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'area_id' => $this->area->id,
        ]);
        $place->place_slug = 'p-'.Str::lower(Str::random(12));
        $place->place_status = $status;
        $place->user_id = $owner->id;
        $place->save();

        return $place;
    }

    private function visitor(): User
    {
        $user = new User;
        $user->user_phone = '059'.str_pad((string) ++self::$counter, 7, '0', STR_PAD_LEFT);
        $user->user_full_name = 'Visitor';
        $user->save();

        return $user;
    }

    private function dayOffset(int $daysAgo): string
    {
        return Carbon::parse(PlaceVisit::today())->subDays($daysAgo)->toDateString();
    }

    private function seedVisits(Place $place, int $daysAgo, int $count): void
    {
        PlaceVisit::create([
            'place_id' => $place->id,
            'visit_date' => $this->dayOffset($daysAgo),
            'visits_count' => $count,
        ]);
    }

    private function postVisit(Place|int $place, string $ip = '10.0.0.1')
    {
        $id = $place instanceof Place ? $place->id : $place;

        return $this->withServerVariables(['REMOTE_ADDR' => $ip])->postJson("/api/places/{$id}/visit");
    }

    // ---------- تسجيل الزيارة ----------

    public function test_guest_visit_is_counted_once_per_day_row(): void
    {
        $place = $this->makePlace();

        $this->postVisit($place)->assertOk()->assertJson(['data' => ['counted' => true]]);

        $this->assertDatabaseCount('place_visits', 1);
        $this->assertDatabaseHas('place_visits', [
            'place_id' => $place->id,
            'visit_date' => PlaceVisit::today(),
            'visits_count' => 1,
        ]);
    }

    public function test_same_visitor_within_window_is_not_counted_again(): void
    {
        $place = $this->makePlace();

        $this->postVisit($place)->assertJson(['data' => ['counted' => true]]);
        $this->postVisit($place)->assertOk()->assertJson(['data' => ['counted' => false]]);

        $this->assertSame(1, (int) PlaceVisit::where('place_id', $place->id)->sum('visits_count'));
    }

    public function test_different_visitors_increment_the_same_daily_row(): void
    {
        $place = $this->makePlace();

        $this->postVisit($place, '10.0.0.1')->assertJson(['data' => ['counted' => true]]);
        $this->postVisit($place, '10.0.0.2')->assertJson(['data' => ['counted' => true]]);

        $this->assertDatabaseCount('place_visits', 1);
        $this->assertSame(2, (int) PlaceVisit::where('place_id', $place->id)->value('visits_count'));
    }

    public function test_logged_in_non_owner_is_counted(): void
    {
        $place = $this->makePlace();

        $this->actingAs($this->visitor())->postJson("/api/places/{$place->id}/visit")
            ->assertOk()->assertJson(['data' => ['counted' => true]]);

        $this->assertSame(1, (int) PlaceVisit::where('place_id', $place->id)->sum('visits_count'));
    }

    public function test_owner_visiting_own_place_is_not_counted(): void
    {
        $place = $this->makePlace();
        $owner = User::findOrFail($place->user_id);

        $this->actingAs($owner)->postJson("/api/places/{$place->id}/visit")
            ->assertOk()->assertJson(['data' => ['counted' => false]]);

        $this->assertDatabaseCount('place_visits', 0);
    }

    public function test_unapproved_or_missing_place_is_404(): void
    {
        $pending = $this->makePlace(Place::STATUS_PENDING);
        $rejected = $this->makePlace(Place::STATUS_REJECTED);

        $this->postVisit($pending)->assertNotFound()->assertJson(['code' => 'NOT_FOUND']);
        $this->postVisit($rejected)->assertNotFound();
        $this->postVisit(999999)->assertNotFound();
        $this->postJson('/api/places/abc/visit')->assertNotFound();

        $this->assertDatabaseCount('place_visits', 0);
    }

    public function test_visit_endpoint_is_rate_limited(): void
    {
        $place = $this->makePlace();

        for ($i = 0; $i < 60; $i++) {
            $this->postVisit($place, '10.9.9.9')->assertOk();
        }

        $this->postVisit($place, '10.9.9.9')->assertStatus(429);
    }

    // ---------- القيود ----------

    public function test_unique_place_and_date_is_enforced(): void
    {
        $place = $this->makePlace();
        $this->seedVisits($place, 0, 1);

        $this->expectException(QueryException::class);
        $this->seedVisits($place, 0, 1);
    }

    public function test_deleting_a_place_removes_its_visits(): void
    {
        $place = $this->makePlace();
        $this->seedVisits($place, 0, 5);

        $place->delete();

        $this->assertDatabaseCount('place_visits', 0);
    }

    // ---------- أرقام لوحة المالك ----------

    public function test_summary_counts_today_week_and_total(): void
    {
        $place = $this->makePlace();
        $this->seedVisits($place, 0, 8);    // اليوم
        $this->seedVisits($place, 6, 12);   // آخر يوم بالأسبوع
        $this->seedVisits($place, 7, 5);    // خارج الأسبوع
        $this->seedVisits($place, 40, 20);  // قديم

        $owner = User::findOrFail($place->user_id);

        $this->actingAs($owner)->getJson('/api/dashboard/visits')
            ->assertOk()
            ->assertExactJson(['data' => ['today' => 8, 'week' => 20, 'total' => 45]]);
    }

    public function test_summary_is_zero_when_no_visits(): void
    {
        $place = $this->makePlace();

        $this->actingAs(User::findOrFail($place->user_id))->getJson('/api/dashboard/visits')
            ->assertOk()
            ->assertExactJson(['data' => ['today' => 0, 'week' => 0, 'total' => 0]]);
    }

    public function test_summary_never_includes_another_places_visits(): void
    {
        $mine = $this->makePlace();
        $other = $this->makePlace();
        $this->seedVisits($mine, 0, 3);
        $this->seedVisits($other, 0, 99);

        $this->actingAs(User::findOrFail($mine->user_id))->getJson('/api/dashboard/visits')
            ->assertOk()
            ->assertJsonPath('data.total', 3);
    }

    public function test_summary_requires_an_approved_store_owner(): void
    {
        $this->getJson('/api/dashboard/visits')->assertUnauthorized();

        $this->actingAs($this->visitor())->getJson('/api/dashboard/visits')
            ->assertForbidden();

        $pending = $this->makePlace(Place::STATUS_PENDING);
        $this->actingAs(User::findOrFail($pending->user_id))->getJson('/api/dashboard/visits')
            ->assertForbidden();
    }

    // ---------- CMS ----------

    public function test_cms_index_ranks_places_by_visits_in_period(): void
    {
        $top = $this->makePlace();
        $low = $this->makePlace();
        $old = $this->makePlace();
        $this->seedVisits($top, 0, 50);
        $this->seedVisits($low, 1, 3);
        $this->seedVisits($old, 20, 70);

        $response = $this->get('/cms/admins/place_visit?period=7')->assertOk();
        $response->assertSeeInOrder([$top->place_name, $low->place_name]);
        $response->assertDontSee($old->place_name);

        $this->get('/cms/admins/place_visit?period=all')->assertOk()->assertSee($old->place_name);
    }

    public function test_cms_index_ignores_invalid_period(): void
    {
        $place = $this->makePlace();
        $this->seedVisits($place, 0, 4);

        $this->get('/cms/admins/place_visit?period=hack')->assertOk()->assertSee($place->place_name);
    }

    public function test_cms_index_is_empty_state_safe(): void
    {
        $this->get('/cms/admins/place_visit')->assertOk()->assertSee('No visits in this period.');
    }
}