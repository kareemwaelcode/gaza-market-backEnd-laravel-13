<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardSubscriber;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

class SubscriberTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/dashboard/subscribers';

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
    private function subscriber(User $owner, array $overrides = []): DashboardSubscriber
    {
        $subscriber = new DashboardSubscriber($overrides + [
            'name' => 'أحمد',
            'country_code' => '970',
            'whatsapp' => '592194533',
            'duration' => 'month',
            'amount' => 50,
            'start_date' => '2026-11-01',
            'note' => null,
        ]);
        $subscriber->place_id = $this->placeOf($owner)->id;
        $subscriber->save();

        return $subscriber;
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'أحمد',
            'countryCode' => '970',
            'whatsapp' => '592194533',
            'duration' => 'month',
            'amount' => 50,
            'startDate' => '2026-11-01',
            'note' => 'ملاحظة',
        ];
    }

    // ---------- الوصول ----------

    public function test_guest_gets_401_on_all_routes(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->postJson(self::URL, $this->payload())->assertStatus(401);
        $this->patchJson(self::URL.'/1', ['name' => 'X'])->assertStatus(401);
        $this->deleteJson(self::URL.'/1')->assertStatus(401);
        $this->postJson(self::URL.'/1/toggle-stop')->assertStatus(401);
        $this->postJson(self::URL.'/1/renew')->assertStatus(401);
    }

    public function test_pending_owner_and_plain_user_get_403(): void
    {
        $this->actingAs($this->owner(status: Place::STATUS_PENDING))->getJson(self::URL)->assertStatus(403);

        $plain = $this->owner();
        $plain->user_role = 'user';
        $plain->save();
        $this->actingAs($plain)->postJson(self::URL, $this->payload())->assertStatus(403);
    }

    public function test_unsupported_place_types_get_404_on_all_routes(): void
    {
        foreach (['cafe', 'restaurant', 'restaurant-cafe', 'store'] as $type) {
            $user = $this->owner($type);

            $this->actingAs($user)->getJson(self::URL)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
            $this->actingAs($user)->postJson(self::URL, $this->payload())->assertStatus(404);
            // 404 يسبق التحقق من المدخلات.
            $this->actingAs($user)->postJson(self::URL, [])->assertStatus(404);
            $this->actingAs($user)->patchJson(self::URL.'/1', ['name' => 'X'])->assertStatus(404);
            $this->actingAs($user)->deleteJson(self::URL.'/1')->assertStatus(404);
            $this->actingAs($user)->postJson(self::URL.'/1/toggle-stop')->assertStatus(404);
            $this->actingAs($user)->postJson(self::URL.'/1/renew')->assertStatus(404);
        }

        $this->assertSame(0, DashboardSubscriber::count());
    }

    public function test_free_and_paid_workspace_owners_both_have_access(): void
    {
        foreach (['free', 'paid'] as $plan) {
            $this->actingAs($this->owner('workspace', $plan))->postJson(self::URL, $this->payload())->assertCreated();
        }

        $this->assertSame(2, DashboardSubscriber::count());
    }

    public function test_non_numeric_ids_do_not_match_routes(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patchJson(self::URL.'/abc', ['name' => 'X'])->assertStatus(404);
        $this->actingAs($user)->deleteJson(self::URL.'/abc')->assertStatus(404);
        $this->actingAs($user)->postJson(self::URL.'/abc/renew')->assertStatus(404);
    }

    // ---------- الإضافة ----------

    public function test_store_creates_subscriber_for_own_place_with_expected_shape(): void
    {
        $user = $this->owner();

        $response = $this->actingAs($user)->postJson(self::URL, $this->payload(['amount' => 12.5]))->assertCreated();

        $data = $response->json('data');
        $this->assertSame('أحمد', $data['name']);
        $this->assertSame('970', $data['countryCode']);
        $this->assertSame('592194533', $data['whatsapp']);
        $this->assertSame('month', $data['duration']);
        $this->assertEquals(12.5, $data['amount']);
        $this->assertSame('2026-11-01', $data['startDate']);
        $this->assertSame('ملاحظة', $data['note']);
        $this->assertFalse($data['stopped']);
        $this->assertIsString($data['id']);
        $this->assertNotNull($data['createdAt']);

        $this->assertDatabaseHas('dashboard_subscribers', [
            'id' => (int) $data['id'],
            'place_id' => $this->placeOf($user)->id,
            'country_code' => '970',
            'start_date' => '2026-11-01',
            'is_stopped' => false,
        ]);
    }

    public function test_store_defaults_empty_amount_to_zero_and_empty_note_to_blank(): void
    {
        $user = $this->owner();

        foreach ([null, ''] as $amount) {
            $data = $this->actingAs($user)
                ->postJson(self::URL, $this->payload(['amount' => $amount, 'note' => '']))
                ->assertCreated()->json('data');

            $this->assertEquals(0, $data['amount']);
            $this->assertSame('', $data['note']);
        }

        $this->assertSame(2, DashboardSubscriber::whereNull('note')->where('amount', 0)->count());
    }

    public function test_store_accepts_boundary_values(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->postJson(self::URL, $this->payload([
            'name' => str_repeat('ا', 80),
            'countryCode' => '972',
            'duration' => 'day',
            'amount' => 999999.99,
            'note' => str_repeat('ب', 300),
        ]))->assertCreated();

        $this->actingAs($user)->postJson(self::URL, $this->payload(['amount' => 0, 'duration' => 'week']))->assertCreated();
    }

    public function test_store_trims_name_and_note(): void
    {
        $data = $this->actingAs($this->owner())
            ->postJson(self::URL, $this->payload(['name' => '  سامي  ', 'note' => '  مهم  ']))
            ->assertCreated()->json('data');

        $this->assertSame('سامي', $data['name']);
        $this->assertSame('مهم', $data['note']);
    }

    public function test_store_rejects_invalid_input_without_creating_anything(): void
    {
        $user = $this->owner();

        $bad = [
            'missing name' => ['name' => null],
            'blank name' => ['name' => '   '],
            'long name' => ['name' => str_repeat('a', 81)],
            'unknown country code' => ['countryCode' => '999'],
            'numeric country code' => ['countryCode' => 970],
            'short whatsapp' => ['whatsapp' => '59219453'],
            'long whatsapp' => ['whatsapp' => '5921945331'],
            'whatsapp not starting with 5' => ['whatsapp' => '692194533'],
            'whatsapp with leading zero' => ['whatsapp' => '0592194533'],
            'whatsapp with letters' => ['whatsapp' => '59219453a'],
            'unknown duration' => ['duration' => 'year'],
            'negative amount' => ['amount' => -1],
            'text amount' => ['amount' => 'abc'],
            'huge amount' => ['amount' => 1000000],
            'three decimals' => ['amount' => 1.234],
            'impossible date' => ['startDate' => '2026-02-31'],
            'text date' => ['startDate' => 'abc'],
            'wrong date format' => ['startDate' => '01/11/2026'],
            'datetime instead of date' => ['startDate' => '2026-11-01 10:00:00'],
            'long note' => ['note' => str_repeat('a', 301)],
        ];

        foreach ($bad as $label => $override) {
            $this->actingAs($user)->postJson(self::URL, $this->payload($override))
                ->assertStatus(422)
                ->assertJsonPath('code', 'VALIDATION');
            $this->assertSame(0, DashboardSubscriber::count(), $label);
        }

        foreach (['name', 'countryCode', 'whatsapp', 'duration', 'startDate'] as $field) {
            $payload = $this->payload();
            unset($payload[$field]);

            $this->actingAs($user)->postJson(self::URL, $payload)->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        }

        $this->assertSame(0, DashboardSubscriber::count());
    }

    public function test_store_ignores_protected_fields(): void
    {
        $mine = $this->owner();
        $other = $this->owner();

        $data = $this->actingAs($mine)->postJson(self::URL, $this->payload([
            'id' => 999999,
            'place_id' => $this->placeOf($other)->id,
            'is_stopped' => true,
            'stopped' => true,
            'created_at' => '2000-01-01 00:00:00',
        ]))->assertCreated()->json('data');

        $row = DashboardSubscriber::findOrFail((int) $data['id']);
        $this->assertNotSame(999999, $row->id);
        $this->assertSame($this->placeOf($mine)->id, $row->place_id);
        $this->assertFalse($row->is_stopped);
        $this->assertNotSame('2000', $row->created_at->format('Y'));
        $this->assertSame(0, DashboardSubscriber::where('place_id', $this->placeOf($other)->id)->count());
    }

    // ---------- القائمة ----------

    public function test_index_returns_only_own_subscribers_newest_first_with_meta(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $first = $this->subscriber($mine, ['name' => 'الأول']);
        $second = $this->subscriber($mine, ['name' => 'الثاني', 'duration' => 'day', 'amount' => 0]);
        $this->subscriber($other, ['name' => 'لغيري']);

        $response = $this->actingAs($mine)->getJson(self::URL)->assertOk();

        $this->assertSame([(string) $second->id, (string) $first->id], array_column($response->json('data'), 'id'));
        $response->assertJsonPath('meta.total', 2)
            ->assertJsonPath('meta.has_more', false)
            ->assertJsonPath('meta.next_cursor', null);
        $this->assertSame('day', $response->json('data.0.duration'));
        $this->assertArrayNotHasKey('place_id', $response->json('data.0'));
    }

    public function test_index_empty_list(): void
    {
        $this->actingAs($this->owner())->getJson(self::URL)->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    public function test_index_paginates_with_cursor_without_duplicates(): void
    {
        $user = $this->owner();
        for ($i = 1; $i <= 5; $i++) {
            $this->subscriber($user, ['name' => 'مشترك '.$i]);
        }

        $first = $this->actingAs($user)->getJson(self::URL.'?limit=2')->assertOk()
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.total', 5);
        $this->assertCount(2, $first->json('data'));

        $ids = array_column($first->json('data'), 'id');
        $cursor = $first->json('meta.next_cursor');
        $this->assertNotNull($cursor);

        while ($cursor !== null) {
            $page = $this->actingAs($user)->getJson(self::URL.'?limit=2&cursor='.urlencode($cursor))->assertOk();
            $ids = array_merge($ids, array_column($page->json('data'), 'id'));
            $cursor = $page->json('meta.next_cursor');
        }

        $this->assertCount(5, $ids);
        $this->assertCount(5, array_unique($ids));
    }

    public function test_index_rejects_invalid_pagination_params(): void
    {
        $user = $this->owner();

        foreach (['limit=0', 'limit=101', 'limit=abc', 'cursor='.str_repeat('a', 501)] as $query) {
            $this->actingAs($user)->getJson(self::URL.'?'.$query)->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        }
    }

    // ---------- التعديل ----------

    public function test_update_changes_only_sent_fields(): void
    {
        $user = $this->owner();
        $subscriber = $this->subscriber($user, ['note' => 'قديمة']);

        $data = $this->actingAs($user)->patchJson(self::URL.'/'.$subscriber->id, [
            'name' => 'اسم جديد',
            'duration' => 'week',
            'amount' => 75.5,
        ])->assertOk()->json('data');

        $this->assertSame('اسم جديد', $data['name']);
        $this->assertSame('week', $data['duration']);
        $this->assertEquals(75.5, $data['amount']);
        // لم تُرسل: تبقى كما هي.
        $this->assertSame('970', $data['countryCode']);
        $this->assertSame('592194533', $data['whatsapp']);
        $this->assertSame('2026-11-01', $data['startDate']);
        $this->assertSame('قديمة', $data['note']);
    }

    public function test_update_can_clear_note_and_reset_amount(): void
    {
        $user = $this->owner();
        $subscriber = $this->subscriber($user, ['note' => 'قديمة', 'amount' => 30]);

        $data = $this->actingAs($user)->patchJson(self::URL.'/'.$subscriber->id, ['note' => '', 'amount' => null])
            ->assertOk()->json('data');

        $this->assertSame('', $data['note']);
        $this->assertEquals(0, $data['amount']);
    }

    public function test_update_cannot_change_stopped_place_or_id(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $subscriber = $this->subscriber($mine);

        $this->actingAs($mine)->patchJson(self::URL.'/'.$subscriber->id, [
            'name' => 'تعديل',
            'stopped' => true,
            'is_stopped' => true,
            'place_id' => $this->placeOf($other)->id,
            'id' => 12345,
        ])->assertOk();

        $fresh = $subscriber->fresh();
        $this->assertFalse($fresh->is_stopped);
        $this->assertSame($this->placeOf($mine)->id, $fresh->place_id);
        $this->assertSame($subscriber->id, $fresh->id);
    }

    public function test_update_rejects_empty_body_and_invalid_values(): void
    {
        $user = $this->owner();
        $subscriber = $this->subscriber($user);
        $url = self::URL.'/'.$subscriber->id;

        $this->actingAs($user)->patchJson($url, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        $this->actingAs($user)->patchJson($url, ['stopped' => true])->assertStatus(422);

        foreach ([['name' => ''], ['whatsapp' => '123'], ['countryCode' => '1'], ['duration' => 'x'], ['amount' => -5], ['startDate' => 'x']] as $bad) {
            $this->actingAs($user)->patchJson($url, $bad)->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        }

        $this->assertSame('أحمد', $subscriber->fresh()->name);
    }

    public function test_cannot_touch_another_owners_subscriber(): void
    {
        $mine = $this->owner();
        $victim = $this->owner();
        $subscriber = $this->subscriber($victim, ['name' => 'محمي']);
        $url = self::URL.'/'.$subscriber->id;

        $this->actingAs($mine)->patchJson($url, ['name' => 'مخترق'])->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->actingAs($mine)->deleteJson($url)->assertStatus(404);
        $this->actingAs($mine)->postJson($url.'/toggle-stop')->assertStatus(404);
        $this->actingAs($mine)->postJson($url.'/renew')->assertStatus(404);

        $fresh = $subscriber->fresh();
        $this->assertNotNull($fresh);
        $this->assertSame('محمي', $fresh->name);
        $this->assertFalse($fresh->is_stopped);
        $this->assertSame('2026-11-01', $fresh->start_date->toDateString());
    }

    public function test_missing_subscriber_returns_404(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->patchJson(self::URL.'/999999', ['name' => 'X'])->assertStatus(404);
        $this->actingAs($user)->deleteJson(self::URL.'/999999')->assertStatus(404);
        $this->actingAs($user)->postJson(self::URL.'/999999/toggle-stop')->assertStatus(404);
        $this->actingAs($user)->postJson(self::URL.'/999999/renew')->assertStatus(404);
    }

    // ---------- الحذف ----------

    public function test_destroy_deletes_permanently(): void
    {
        $user = $this->owner();
        $subscriber = $this->subscriber($user);
        $keep = $this->subscriber($user, ['name' => 'يبقى']);

        $this->actingAs($user)->deleteJson(self::URL.'/'.$subscriber->id)->assertNoContent();

        $this->assertDatabaseMissing('dashboard_subscribers', ['id' => $subscriber->id]);
        $this->assertDatabaseHas('dashboard_subscribers', ['id' => $keep->id]);
        $this->actingAs($user)->deleteJson(self::URL.'/'.$subscriber->id)->assertStatus(404);
    }

    public function test_deleting_place_removes_its_subscribers(): void
    {
        $user = $this->owner();
        $this->subscriber($user);

        $this->placeOf($user)->delete();

        $this->assertSame(0, DashboardSubscriber::count());
    }

    // ---------- الإيقاف ----------

    public function test_toggle_stop_flips_and_restores(): void
    {
        $user = $this->owner();
        $subscriber = $this->subscriber($user);
        $url = self::URL.'/'.$subscriber->id.'/toggle-stop';

        $this->actingAs($user)->postJson($url)->assertOk()->assertJsonPath('data.stopped', true);
        $this->assertTrue($subscriber->fresh()->is_stopped);

        $this->actingAs($user)->postJson($url)->assertOk()->assertJsonPath('data.stopped', false);
        $this->assertFalse($subscriber->fresh()->is_stopped);
    }

    public function test_toggle_stop_keeps_other_fields_untouched(): void
    {
        $user = $this->owner();
        $subscriber = $this->subscriber($user, ['name' => 'ثابت', 'amount' => 40]);

        $this->actingAs($user)->postJson(self::URL.'/'.$subscriber->id.'/toggle-stop')->assertOk();

        $fresh = $subscriber->fresh();
        $this->assertSame('ثابت', $fresh->name);
        $this->assertSame('2026-11-01', $fresh->start_date->toDateString());
        $this->assertEquals(40, $fresh->amount);
    }

    // ---------- التجديد ----------

    public function test_renew_sets_start_date_to_today_in_gaza_and_reactivates(): void
    {
        $user = $this->owner();
        $subscriber = $this->subscriber($user, ['start_date' => '2026-01-01']);
        $subscriber->is_stopped = true;
        $subscriber->save();

        // 22:30 UTC = 00:30 اليوم التالي بتوقيت غزة (UTC+2 بالشتاء).
        $this->travelTo(Carbon::parse('2026-11-16 22:30:00', 'UTC'));

        $data = $this->actingAs($user)->postJson(self::URL.'/'.$subscriber->id.'/renew')->assertOk()->json('data');

        $this->travelBack();

        $this->assertSame('2026-11-17', $data['startDate']);
        $this->assertFalse($data['stopped']);

        $fresh = $subscriber->fresh();
        $this->assertSame('2026-11-17', $fresh->start_date->toDateString());
        $this->assertFalse($fresh->is_stopped);
    }

    public function test_renew_keeps_name_duration_and_amount(): void
    {
        $user = $this->owner();
        $subscriber = $this->subscriber($user, ['name' => 'ثابت', 'duration' => 'week', 'amount' => 25]);

        $this->actingAs($user)->postJson(self::URL.'/'.$subscriber->id.'/renew')->assertOk();

        $fresh = $subscriber->fresh();
        $this->assertSame('ثابت', $fresh->name);
        $this->assertSame('week', $fresh->duration);
        $this->assertEquals(25, $fresh->amount);
    }
}