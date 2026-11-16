<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardAd;
use App\Models\Place;
use App\Models\Regions;
use App\Models\StoreCategory;
use App\Models\StoreCategoryGroup;
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

    private StoreCategoryGroup $group;

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

        $this->group = new StoreCategoryGroup;
        $this->group->forceFill([
            'store_category_group_slug' => 'g-'.Str::random(6),
            'store_category_group_name_en' => 'Group',
            'store_category_group_name_ar' => 'مجموعة',
        ])->save();
    }

    /** $variant = تصنيف المتجر (null = متجر عام بلا تصنيف). */
    private function owner(string $type = 'store', string $status = Place::STATUS_APPROVED, ?string $variant = null, string $plan = 'free'): User
    {
        $n = ++self::$counter;
        $user = UserFactory::new()->storeOwner()->create();

        $categoryId = null;

        if ($variant !== null) {
            $category = new StoreCategory;
            $category->forceFill([
                'store_category_slug' => 'c-'.$n.Str::lower(Str::random(5)),
                'store_category_variant_key' => $variant,
                'store_category_name_en' => 'Cat '.$n,
                'store_category_name_ar' => 'تصنيف '.$n,
                'store_category_is_active' => true,
                'store_category_group_id' => $this->group->id,
            ])->save();
            $categoryId = $category->id;
        }

        $place = new Place;
        $place->fill([
            'place_name' => 'Place '.$n,
            'place_type' => $type,
            'place_address' => 'Street '.$n,
            'place_phone' => '056'.str_pad((string) $n, 7, '0', STR_PAD_LEFT),
            'place_whatsapp' => '970593'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
            'area_id' => $this->area->id,
            'store_category_id' => $categoryId,
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
                ->postJson(self::URL, ['type' => 'offer', 'title' => 'إعلان'])
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
        $user = $this->owner(plan: 'paid');

        for ($i = 1; $i <= 5; $i++) {
            $this->ad($user, "إعلان $i");
        }

        $this->actingAs($user)->getJson(self::URL)->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.used', 5);
    }

    // ---------- الإضافة ----------

    public function test_store_creates_an_offer_with_all_fields_for_a_specific_store(): void
    {
        $user = $this->owner(variant: 'menswear');

        $this->actingAs($user)->postJson(self::URL, [
            'type' => 'offer',
            'title' => '  خصم نهاية الموسم  ',
            'details' => 'على جميع الأصناف',
            'date' => '2026-12-01',
            'price_before' => 100,
            'price_after' => 79.5,
            'is_hidden' => true,
        ])->assertCreated()
            ->assertJsonPath('data.type', 'offer')
            ->assertJsonPath('data.title', 'خصم نهاية الموسم')
            ->assertJsonPath('data.details', 'على جميع الأصناف')
            ->assertJsonPath('data.date', '2026-12-01')
            ->assertJsonPath('data.link', null)
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
            'type' => 'activity', 'title' => 'افتتاح', 'details' => '  ', 'date' => '', 'link' => '', 'price_before' => '', 'price_after' => '',
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

    public function test_offer_in_a_specific_store_needs_both_prices_with_after_lower_than_before(): void
    {
        $user = $this->owner(variant: 'menswear');

        foreach ([[100, 100], [100, 150]] as [$before, $after]) {
            $this->actingAs($user)->postJson(self::URL, [
                'type' => 'offer', 'title' => 'X', 'price_before' => $before, 'price_after' => $after,
            ])->assertStatus(422)->assertJsonPath('code', 'VALIDATION')->assertJsonStructure(['errors' => ['price_after']]);
        }

        // الفرونت يرفض الحفظ بدون السعرين.
        $this->actingAs($user)->postJson(self::URL, ['type' => 'offer', 'title' => 'X', 'price_after' => 50])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['price_before']]);
        $this->actingAs($user)->postJson(self::URL, ['type' => 'offer', 'title' => 'X'])
            ->assertStatus(422);

        $this->assertSame(0, DashboardAd::count());
    }

    public function test_prices_are_dropped_where_the_frontend_does_not_offer_them(): void
    {
        $cases = [
            // [place_type, variant, type]
            ['store', null, 'offer'],          // متجر عام
            ['restaurant', null, 'offer'],
            ['cafe', null, 'offer'],
            ['store', 'menswear', 'job'],      // السعر لإعلان offer فقط
        ];

        foreach ($cases as [$placeType, $variant, $type]) {
            $this->actingAs($this->owner($placeType, variant: $variant))->postJson(self::URL, [
                'type' => $type, 'title' => 'X', 'price_before' => 100, 'price_after' => 80,
            ])->assertCreated()
                ->assertJsonPath('data.priceBefore', null)
                ->assertJsonPath('data.priceAfter', null);
        }
    }

    public function test_link_is_kept_only_for_jobs_in_restaurants_and_specific_stores(): void
    {
        $link = 'https://example.com/apply';

        foreach ([['restaurant', null], ['store', 'menswear']] as [$placeType, $variant]) {
            $user = $this->owner($placeType, variant: $variant);

            $this->actingAs($user)->postJson(self::URL, ['type' => 'job', 'title' => 'وظيفة', 'link' => $link])
                ->assertCreated()->assertJsonPath('data.link', $link);

            $offer = ['type' => 'offer', 'title' => 'عرض', 'link' => $link];
            if ($placeType === 'store') {
                $offer += ['price_before' => 10, 'price_after' => 5];
            }
            $this->actingAs($user)->postJson(self::URL, $offer)
                ->assertCreated()->assertJsonPath('data.link', null);
        }

        // كافيه ومساحة عمل ومتجر عام: الرابط لكل الأنواع.
        foreach ([['cafe', null, 'offer'], ['workspace', null, 'workshop'], ['store', null, 'offer']] as [$placeType, $variant, $type]) {
            $this->actingAs($this->owner($placeType, variant: $variant))
                ->postJson(self::URL, ['type' => $type, 'title' => 'X', 'link' => $link])
                ->assertCreated()->assertJsonPath('data.link', $link);
        }
    }

    public function test_allowed_ad_types_follow_the_place_type(): void
    {
        $matrix = [
            ['workspace', null, ['activity', 'workshop', 'job', 'offer'], []],
            ['restaurant', null, ['offer', 'job'], ['activity', 'workshop']],
            ['cafe', null, ['activity', 'offer'], ['workshop', 'job']],
            ['restaurant-cafe', null, ['activity', 'offer'], ['workshop', 'job']],
            ['store', 'menswear', ['offer', 'job'], ['activity', 'workshop']],
            ['store', null, ['activity', 'workshop', 'job', 'offer'], []],
        ];

        foreach ($matrix as [$placeType, $variant, $allowed, $denied]) {
            $user = $this->owner($placeType, variant: $variant, plan: 'paid');

            foreach ($allowed as $type) {
                $payload = ['type' => $type, 'title' => 'X'];
                if ($type === 'offer' && $placeType === 'store' && $variant !== null) {
                    $payload += ['price_before' => 10, 'price_after' => 5];
                }
                $this->actingAs($user)->postJson(self::URL, $payload)->assertCreated();
            }

            foreach ($denied as $type) {
                $this->actingAs($user)->postJson(self::URL, ['type' => $type, 'title' => 'X'])
                    ->assertStatus(422)->assertJsonPath('code', 'VALIDATION')->assertJsonStructure(['errors' => ['type']]);
            }
        }
    }

    public function test_old_unknown_types_are_rejected(): void
    {
        $user = $this->owner('workspace');

        foreach (['discount', 'announcement', 'event'] as $type) {
            $this->actingAs($user)->postJson(self::URL, ['type' => $type, 'title' => 'X'])->assertStatus(422);
        }
    }

    public function test_free_plan_is_limited_to_three_ads_and_paid_is_not(): void
    {
        $free = $this->owner();

        for ($i = 1; $i <= 3; $i++) {
            $this->actingAs($free)->postJson(self::URL, ['type' => 'offer', 'title' => "إعلان $i"])->assertCreated();
        }

        $this->actingAs($free)->postJson(self::URL, ['type' => 'offer', 'title' => 'الرابع'])
            ->assertStatus(403)->assertJsonPath('code', 'PLAN_LIMIT_REACHED')->assertJsonPath('limit', 3);

        $this->actingAs($free)->getJson(self::URL)->assertOk()
            ->assertJsonPath('meta.limit', 3)->assertJsonPath('meta.used', 3);

        // الحذف يُرجع مكاناً (الفرونت يحسب الإعلانات الحالية).
        DashboardAd::where('place_id', $this->placeOf($free)->id)->first()->delete();
        $this->actingAs($free)->postJson(self::URL, ['type' => 'offer', 'title' => 'بديل'])->assertCreated();

        $paid = $this->owner(plan: 'paid');

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($paid)->postJson(self::URL, ['type' => 'offer', 'title' => "إعلان $i"])->assertCreated();
        }

        $this->actingAs($paid)->getJson(self::URL)->assertOk()->assertJsonPath('meta.limit', null);
    }

    public function test_hard_cap_blocks_new_ads_per_place(): void
    {
        config(['dashboard_plan.ads.hard_cap' => 2]);
        $a = $this->owner(plan: 'paid');
        $b = $this->owner(plan: 'paid');

        $this->actingAs($a)->postJson(self::URL, ['type' => 'offer', 'title' => '1'])->assertCreated();
        $this->actingAs($a)->postJson(self::URL, ['type' => 'offer', 'title' => '2'])->assertCreated();
        $this->actingAs($a)->postJson(self::URL, ['type' => 'offer', 'title' => '3'])
            ->assertStatus(403)->assertJsonPath('code', 'PLAN_LIMIT_REACHED')->assertJsonPath('limit', null);

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
        $user = $this->owner(variant: 'menswear');
        $ad = $this->ad($user, 'عرض', ['price_before' => 100, 'price_after' => 80]);

        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['title' => 'عرض جديد', 'date' => '2026-12-10'])
            ->assertOk()
            ->assertJsonPath('data.title', 'عرض جديد')
            ->assertJsonPath('data.date', '2026-12-10')
            ->assertJsonPath('data.priceBefore', 100)
            ->assertJsonPath('data.priceAfter', 80)
            ->assertJsonPath('data.isHidden', false);

        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['is_hidden' => true])
            ->assertOk()->assertJsonPath('data.isHidden', true);
    }

    public function test_update_checks_price_order_against_stored_values(): void
    {
        $user = $this->owner(variant: 'menswear');
        $ad = $this->ad($user, 'عرض', ['price_before' => 100, 'price_after' => 80]);

        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['price_after' => 120])
            ->assertStatus(422)->assertJsonStructure(['errors' => ['price_after']]);
        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['price_before' => 50])
            ->assertStatus(422);

        $this->assertSame('80.00', $ad->refresh()->price_after);
        $this->assertSame('100.00', $ad->price_before);
    }

    public function test_update_to_a_job_clears_prices_and_to_an_unavailable_type_is_rejected(): void
    {
        $user = $this->owner(variant: 'menswear');
        $ad = $this->ad($user, 'عرض', ['price_before' => 100, 'price_after' => 80]);

        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['type' => 'activity'])->assertStatus(422)
            ->assertJsonStructure(['errors' => ['type']]);

        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['type' => 'job', 'link' => 'https://example.com/apply'])
            ->assertOk()
            ->assertJsonPath('data.type', 'job')
            ->assertJsonPath('data.priceBefore', null)
            ->assertJsonPath('data.priceAfter', null)
            ->assertJsonPath('data.link', 'https://example.com/apply');

        // العودة إلى عرض تتطلب السعرين.
        $this->actingAs($user)->patchJson(self::URL.'/'.$ad->id, ['type' => 'offer'])->assertStatus(422);
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
        $this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, ['type' => 'discount'])->assertStatus(422);
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