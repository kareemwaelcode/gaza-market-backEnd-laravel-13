<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardAd;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/dashboard/ads';

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

    private function owner(string $type = 'store', string $status = Place::STATUS_APPROVED): User
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
        ]);
        $place->place_slug = 'p-'.Str::lower(Str::random(12));
        $place->place_status = $status;
        $place->user_id = $user->id;
        $place->save();

        return $user;
    }

    private function placeOf(User $user): Place
    {
        return Place::where('user_id', $user->id)->firstOrFail();
    }

    private function ad(User $owner, string $title = 'عرض الصيف', array $extra = []): DashboardAd
    {
        $ad = new DashboardAd(array_merge(['type' => 'offer', 'title' => $title], $extra));
        $ad->place_id = $this->placeOf($owner)->id;
        $ad->save();

        return $ad;
    }

    // ---------- الوصول ----------

    public function test_guest_gets_401_on_all_routes(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->postJson(self::URL, ['type' => 'offer', 'title' => 'X'])->assertStatus(401);
        $this->getJson(self::URL.'/1')->assertStatus(401);
        $this->patchJson(self::URL.'/1', ['title' => 'X'])->assertStatus(401);
        $this->deleteJson(self::URL.'/1')->assertStatus(401);
    }

    public function test_pending_owner_and_plain_user_get_403(): void
    {
        $this->actingAs($this->owner(status: Place::STATUS_PENDING))->getJson(self::URL)->assertStatus(403);

        $plain = $this->owner();
        $plain->user_role = 'user';
        $plain->save();
        $this->actingAs($plain)->postJson(self::URL, ['type' => 'offer', 'title' => 'X'])->assertStatus(403);

        $this->assertSame(0, DashboardAd::count());
    }

    public function test_every_place_type_can_create_an_ad(): void
    {
        foreach (['workspace', 'cafe', 'restaurant', 'restaurant-cafe', 'store'] as $type) {
            $this->actingAs($this->owner($type))
                ->postJson(self::URL, ['type' => 'announcement', 'title' => 'إعلان'])
                ->assertCreated();
        }

        $this->assertSame(5, DashboardAd::count());
    }

    // ---------- القائمة ----------

    public function test_index_returns_only_own_ads_newest_first_with_meta(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $this->ad($mine, 'قديم');
        $this->ad($mine, 'جديد');
        $this->ad($other, 'لغيري');

        $response = $this->actingAs($mine)->getJson(self::URL)->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.title', 'جديد')
            ->assertJsonPath('data.1.title', 'قديم')
            ->assertJsonPath('meta.used', 2)
            ->assertJsonMissingPath('data.0.place_id');

        $this->assertIsString($response->json('data.0.id'));
    }

    public function test_index_never_exceeds_the_hard_cap(): void
    {
        config(['dashboard_plan.ads.hard_cap' => 3]);
        $user = $this->owner();

        for ($i = 1; $i <= 5; $i++) {
            $this->ad($user, "إعلان $i");
        }

        $this->actingAs($user)->getJson(self::URL)->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.used', 5);
    }

    // ---------- الإضافة ----------

    public function test_store_creates_an_ad_with_all_fields(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->postJson(self::URL, [
            'type' => 'discount',
            'title' => '  خصم نهاية الموسم  ',
            'details' => 'على جميع الأصناف',
            'date' => '2026-12-01',
            'link' => 'https://example.com/offer',
            'price_before' => 100,
            'price_after' => 79.5,
            'is_hidden' => true,
        ])->assertCreated()
            ->assertJsonPath('data.type', 'discount')
            ->assertJsonPath('data.title', 'خصم نهاية الموسم')
            ->assertJsonPath('data.details', 'على جميع الأصناف')
            ->assertJsonPath('data.date', '2026-12-01')
            ->assertJsonPath('data.link', 'https://example.com/offer')
            ->assertJsonPath('data.priceBefore', 100)
            ->assertJsonPath('data.priceAfter', 79.5)
            ->assertJsonPath('data.isHidden', true)
            ->assertJsonStructure(['data' => ['id', 'type', 'title', 'details', 'date', 'link', 'priceBefore', 'priceAfter', 'isHidden', 'createdAt']]);

        $this->assertDatabaseHas('dashboard_ads', [
            'place_id' => $this->placeOf($user)->id,
            'title' => 'خصم نهاية الموسم',
            'is_hidden' => 1,
        ]);
    }

    public function test_store_allows_minimal_ad_with_defaults(): void
    {
        $this->actingAs($this->owner())->postJson(self::URL, [
            'type' => 'event', 'title' => 'افتتاح', 'details' => '  ', 'date' => '', 'link' => '', 'price_before' => '', 'price_after' => '',
        ])->assertCreated()
            ->assertJsonPath('data.details', null)
            ->assertJsonPath('data.date', null)
            ->assertJsonPath('data.link', null)
            ->assertJsonPath('data.priceBefore', null)
            ->assertJsonPath('data.priceAfter', null)
            ->assertJsonPath('data.isHidden', false);
    }

    public function test_store_ignores_place_id_sent_by_the_client(): void
    {
        $mine = $this->owner();
        $other = $this->owner();

        $this->actingAs($mine)->postJson(self::URL, [
            'type' => 'offer', 'title' => 'X', 'place_id' => $this->placeOf($other)->id,
        ])->assertCreated();

        $this->assertSame(1, DashboardAd::where('place_id', $this->placeOf($mine)->id)->count());
        $this->assertSame(0, DashboardAd::where('place_id', $this->placeOf($other)->id)->count());
    }

    public function test_store_validates_input(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->postJson(self::URL, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');

        $ok = ['type' => 'offer', 'title' => 'X'];
        $bad = [
            ['title' => 'X'],
            ['type' => 'offer'],
            ['type' => 'hack', 'title' => 'X'],
            ['type' => 'offer', 'title' => '   '],
            ['type' => 'offer', 'title' => str_repeat('ا', 101)],
            $ok + ['details' => str_repeat('ا', 301)],
            $ok + ['date' => '01/12/2026'],
            $ok + ['date' => '2026-13-45'],
            $ok + ['link' => 'javascript:alert(1)'],
            $ok + ['link' => 'ftp://example.com/x'],
            $ok + ['link' => 'not a url'],
            $ok + ['link' => 'https://example.com/'.str_repeat('a', 250)],
            $ok + ['price_before' => -1],
            $ok + ['price_before' => 'abc'],
            $ok + ['price_before' => 1000000],
            $ok + ['price_after' => 1.234],
            $ok + ['is_hidden' => 'maybe'],
        ];

        foreach ($bad as $payload) {
            $this->actingAs($user)->postJson(self::URL, $payload)->assertStatus(422);
        }

        $this->assertSame(0, DashboardAd::count());
    }

    public function test_store_rejects_price_after_not_lower_than_price_before(): void
    {
        $user = $this->owner();

        foreach ([[100, 100], [100, 150]] as [$before, $after]) {
            $this->actingAs($user)->postJson(self::URL, [
                'type' => 'discount', 'title' => 'X', 'price_before' => $before, 'price_after' => $after,
            ])->assertStatus(422)->assertJsonPath('code', 'VALIDATION')->assertJsonStructure(['errors' => ['price_after']]);
        }

        // سعر واحد فقط مسموح.
        $this->actingAs($user)->postJson(self::URL, ['type' => 'offer', 'title' => 'X', 'price_after' => 50])->assertCreated();
        $this->assertSame(1, DashboardAd::count());
    }

    public function test_hard_cap_blocks_new_ads_per_place(): void
    {
        config(['dashboard_plan.ads.hard_cap' => 2]);
        $a = $this->owner();
        $b = $this->owner();

        $this->actingAs($a)->postJson(self::URL, ['type' => 'offer', 'title' => '1'])->assertCreated();
        $this->actingAs($a)->postJson(self::URL, ['type' => 'offer', 'title' => '2'])->assertCreated();
        $this->actingAs($a)->postJson(self::URL, ['type' => 'offer', 'title' => '3'])
            ->assertStatus(403)->assertJsonPath('code', 'ADS_LIMIT_REACHED');

        $this->assertSame(2, DashboardAd::where('place_id', $this->placeOf($a)->id)->count());

        // السقف لكل محل.
        $this->actingAs($b)->postJson(self::URL, ['type' => 'offer', 'title' => 'X'])->assertCreated();
    }

    // ---------- العرض والتعديل والحذف ----------

    public function test_show_returns_own_ad_and_404_for_others_or_missing(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $own = $this->ad($mine);
        $foreign = $this->ad($other);

        $this->actingAs($mine)->getJson(self::URL.'/'.$own->id)->assertOk()->assertJsonPath('data.id', (string) $own->id);
        $this->actingAs($mine)->getJson(self::URL.'/'.$foreign->id)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->actingAs($mine)->getJson(self::URL.'/999999')->assertStatus(404);
    }

    public function test_update_changes_only_sent_fields_and_can_hide_the_ad(): void
    {
        $user = $this->owner();
        $ad = $this->ad($user, 'عرض', ['price_before' => 100, 'price_after' => 80]);

        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['title' => 'عرض جديد', 'link' => 'https://example.com'])
            ->assertOk()
            ->assertJsonPath('data.title', 'عرض جديد')
            ->assertJsonPath('data.link', 'https://example.com')
            ->assertJsonPath('data.priceBefore', 100)
            ->assertJsonPath('data.priceAfter', 80)
            ->assertJsonPath('data.isHidden', false);

        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['is_hidden' => true])
            ->assertOk()->assertJsonPath('data.isHidden', true);

        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['link' => null, 'price_after' => null])
            ->assertOk()->assertJsonPath('data.link', null)->assertJsonPath('data.priceAfter', null);
    }

    public function test_update_checks_price_order_against_stored_values(): void
    {
        $user = $this->owner();
        $ad = $this->ad($user, 'عرض', ['price_before' => 100, 'price_after' => 80]);

        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['price_after' => 120])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['price_after']]);
        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['price_before' => 50])
            ->assertStatus(422);

        $this->assertSame('80.00', $ad->refresh()->price_after);
        $this->assertSame('100.00', $ad->price_before);
    }

    public function test_update_rejects_empty_body_invalid_data_and_foreign_ad(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $own = $this->ad($mine);
        $foreign = $this->ad($other, 'لغيري');

        $this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        $this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, ['title' => ''])->assertStatus(422);
        $this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, ['type' => 'hack'])->assertStatus(422);
        $this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, ['link' => 'javascript:alert(1)'])->assertStatus(422);
        $this->actingAs($mine)->patchJson(self::URL.'/'.$foreign->id, ['title' => 'اختراق'])->assertStatus(404);

        $this->assertSame('لغيري', $foreign->refresh()->title);
    }

    public function test_update_ignores_place_id_sent_by_the_client(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $ad = $this->ad($mine);

        $this->actingAs($mine)->patchJson(self::URL.'/'.$ad->id, ['title' => 'Y', 'place_id' => $this->placeOf($other)->id])->assertOk();

        $this->assertSame($this->placeOf($mine)->id, $ad->refresh()->place_id);
    }

    public function test_destroy_deletes_own_ad_only(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $own = $this->ad($mine);
        $foreign = $this->ad($other);

        $this->actingAs($mine)->deleteJson(self::URL.'/'.$foreign->id)->assertStatus(404);
        $this->assertDatabaseHas('dashboard_ads', ['id' => $foreign->id]);

        $this->actingAs($mine)->deleteJson(self::URL.'/'.$own->id)->assertNoContent();
        $this->assertDatabaseMissing('dashboard_ads', ['id' => $own->id]);
    }

    public function test_deleting_a_place_deletes_its_ads(): void
    {
        $user = $this->owner();
        $this->ad($user);
        $this->ad($user, 'ثاني');

        $this->placeOf($user)->delete();

        $this->assertSame(0, DashboardAd::count());
    }
}