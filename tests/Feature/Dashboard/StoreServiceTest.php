<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardItemCategory;
use App\Models\DashboardStoreService;
use App\Models\Place;
use App\Models\Regions;
use App\Models\StoreCategory;
use App\Models\StoreCategoryGroup;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreServiceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/dashboard/store-services';

    private const SERVICE_VARIANTS = [
        'tailoring', 'solar', 'repair', 'ironalu', 'salonmen',
        'salonwomen', 'laundry', 'photography', 'garage', 'vet',
    ];

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

    /** $variant = null يعني محلاً بلا تصنيف متجر (يُعامل كـ general). */
    private function owner(?string $variant = 'tailoring', string $plan = 'free', string $type = 'store', string $status = Place::STATUS_APPROVED): User
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

    private function category(User $owner, string $kind = 'service'): DashboardItemCategory
    {
        $category = new DashboardItemCategory(['kind' => $kind, 'name' => 'تصنيف '.Str::random(4)]);
        $category->place_id = $this->placeOf($owner)->id;
        $category->save();

        return $category;
    }

    private function service(User $owner, string $name = 'تقصير', ?float $price = 15.0): DashboardStoreService
    {
        $service = new DashboardStoreService(['name' => $name, 'price' => $price]);
        $service->place_id = $this->placeOf($owner)->id;
        $service->save();

        return $service;
    }

    // ---------- الوصول ----------

    public function test_guest_gets_401_on_all_routes(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->postJson(self::URL, ['name' => 'X'])->assertStatus(401);
        $this->getJson(self::URL.'/1')->assertStatus(401);
        $this->patchJson(self::URL.'/1', ['name' => 'X'])->assertStatus(401);
        $this->deleteJson(self::URL.'/1')->assertStatus(401);
    }

    public function test_pending_owner_and_plain_user_get_403(): void
    {
        $this->actingAs($this->owner(status: Place::STATUS_PENDING))->getJson(self::URL)->assertStatus(403);

        $plain = $this->owner();
        $plain->user_role = 'user';
        $plain->save();
        $this->actingAs($plain)->postJson(self::URL, ['name' => 'X'])->assertStatus(403);
    }

    public function test_non_store_places_have_no_services(): void
    {
        foreach (['cafe', 'restaurant', 'restaurant-cafe', 'workspace'] as $type) {
            $user = $this->owner('tailoring', 'free', $type);

            $this->actingAs($user)->getJson(self::URL)->assertStatus(403)->assertJsonPath('code', 'STORE_SERVICES_NOT_SUPPORTED');
            $this->actingAs($user)->postJson(self::URL, ['name' => 'X'])->assertStatus(403);
        }

        $this->assertSame(0, DashboardStoreService::count());
    }

    public function test_variants_without_service_items_are_rejected(): void
    {
        foreach (['general', 'grocery', 'menswear', 'clinic', 'pharmacy'] as $variant) {
            $user = $this->owner($variant);

            $this->actingAs($user)->getJson(self::URL)->assertStatus(403)->assertJsonPath('code', 'STORE_SERVICES_NOT_SUPPORTED');
            $this->actingAs($user)->postJson(self::URL, ['name' => 'X'])->assertStatus(403);
        }

        $this->actingAs($this->owner(null))->postJson(self::URL, ['name' => 'X'])->assertStatus(403);
        $this->assertSame(0, DashboardStoreService::count());
    }

    public function test_every_service_variant_can_create_a_service(): void
    {
        foreach (self::SERVICE_VARIANTS as $variant) {
            $this->actingAs($this->owner($variant))->postJson(self::URL, ['name' => 'خدمة'])->assertCreated();
        }

        $this->assertSame(count(self::SERVICE_VARIANTS), DashboardStoreService::count());
    }

    // ---------- القائمة ----------

    public function test_index_returns_only_own_services_newest_first_with_meta(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $this->service($mine, 'قديمة');
        $this->service($mine, 'جديدة');
        $this->service($other, 'لغيري');

        $response = $this->actingAs($mine)->getJson(self::URL)->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'جديدة')
            ->assertJsonPath('data.1.name', 'قديمة')
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.used', 2)
            ->assertJsonMissingPath('data.0.place_id');

        $this->assertIsString($response->json('data.0.id'));
    }

    public function test_index_meta_limit_is_null_for_paid(): void
    {
        $this->actingAs($this->owner('tailoring', 'paid'))->getJson(self::URL)
            ->assertOk()->assertJsonPath('meta.limit', null);
    }

    // ---------- الإضافة ----------

    public function test_store_creates_a_service_with_all_fields(): void
    {
        $user = $this->owner();
        $category = $this->category($user);

        $this->actingAs($user)->postJson(self::URL, [
            'name' => '  تفصيل بدلة  ',
            'description' => 'قماش حسب الطلب',
            'price' => 300.5,
            'price_from' => true,
            'duration' => 'أسبوع',
            'item_category_id' => $category->id,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'تفصيل بدلة')
            ->assertJsonPath('data.description', 'قماش حسب الطلب')
            ->assertJsonPath('data.price', 300.5)
            ->assertJsonPath('data.priceFrom', true)
            ->assertJsonPath('data.duration', 'أسبوع')
            ->assertJsonPath('data.categoryId', (string) $category->id)
            ->assertJsonStructure(['data' => ['id', 'name', 'description', 'price', 'priceFrom', 'duration', 'categoryId', 'createdAt']]);

        $this->assertDatabaseHas('dashboard_store_services', [
            'place_id' => $this->placeOf($user)->id,
            'name' => 'تفصيل بدلة',
            'price_from' => 1,
        ]);
    }

    public function test_store_allows_minimal_service_with_defaults(): void
    {
        $this->actingAs($this->owner())->postJson(self::URL, ['name' => 'تقصير', 'price' => '', 'description' => '  ', 'duration' => ''])
            ->assertCreated()
            ->assertJsonPath('data.price', null)
            ->assertJsonPath('data.description', null)
            ->assertJsonPath('data.duration', null)
            ->assertJsonPath('data.categoryId', null)
            ->assertJsonPath('data.priceFrom', false);
    }

    public function test_price_from_is_dropped_when_there_is_no_price(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->postJson(self::URL, ['name' => 'X', 'price_from' => true])
            ->assertCreated()->assertJsonPath('data.priceFrom', false);

        $service = $this->service($user, 'Y', 20.0);
        $this->actingAs($user)->patchJson(self::URL.'/'.$service->id, ['price_from' => true])
            ->assertOk()->assertJsonPath('data.priceFrom', true);

        $this->actingAs($user)->patchJson(self::URL.'/'.$service->id, ['price' => null])
            ->assertOk()->assertJsonPath('data.price', null)->assertJsonPath('data.priceFrom', false);
    }

    public function test_store_ignores_place_id_sent_by_the_client(): void
    {
        $mine = $this->owner();
        $other = $this->owner();

        $this->actingAs($mine)->postJson(self::URL, [
            'name' => 'قص',
            'place_id' => $this->placeOf($other)->id,
        ])->assertCreated();

        $this->assertSame(1, DashboardStoreService::where('place_id', $this->placeOf($mine)->id)->count());
        $this->assertSame(0, DashboardStoreService::where('place_id', $this->placeOf($other)->id)->count());
    }

    public function test_store_validates_input(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->postJson(self::URL, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');

        $bad = [
            ['name' => '   '],
            ['name' => str_repeat('ا', 101)],
            ['name' => 'X', 'price' => -1],
            ['name' => 'X', 'price' => 'abc'],
            ['name' => 'X', 'price' => 1000000],
            ['name' => 'X', 'price' => 1.234],
            ['name' => 'X', 'price_from' => 'maybe'],
            ['name' => 'X', 'description' => str_repeat('ا', 301)],
            ['name' => 'X', 'duration' => str_repeat('ا', 41)],
            ['name' => 'X', 'item_category_id' => 'abc'],
        ];

        foreach ($bad as $payload) {
            $this->actingAs($user)->postJson(self::URL, $payload)->assertStatus(422);
        }

        $this->assertSame(0, DashboardStoreService::count());
    }

    public function test_store_rejects_foreign_or_wrong_kind_category(): void
    {
        $mine = $this->owner();
        $other = $this->owner();

        foreach ([$this->category($other), $this->category($mine, 'product'), $this->category($mine, 'menu'), 99999] as $category) {
            $id = $category instanceof DashboardItemCategory ? $category->id : $category;

            $this->actingAs($mine)->postJson(self::URL, ['name' => 'X', 'item_category_id' => $id])
                ->assertStatus(422)->assertJsonStructure(['errors' => ['item_category_id']]);
        }

        $this->assertSame(0, DashboardStoreService::count());
    }

    // ---------- حدود الباقة ----------

    public function test_free_limit_is_10_and_paid_is_unlimited(): void
    {
        $free = $this->owner();

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($free)->postJson(self::URL, ['name' => "خدمة $i"])->assertCreated();
        }

        $this->actingAs($free)->postJson(self::URL, ['name' => 'الحادية عشرة'])
            ->assertStatus(403)->assertJsonPath('code', 'PLAN_LIMIT_REACHED')->assertJsonPath('limit', 10);

        $this->assertSame(10, DashboardStoreService::where('place_id', $this->placeOf($free)->id)->count());

        $paid = $this->owner('tailoring', 'paid');

        for ($i = 1; $i <= 11; $i++) {
            $this->actingAs($paid)->postJson(self::URL, ['name' => "خدمة $i"])->assertCreated();
        }
    }

    public function test_limit_is_per_place(): void
    {
        $a = $this->owner();
        $b = $this->owner();

        for ($i = 1; $i <= 10; $i++) {
            $this->service($a, "خدمة $i");
        }

        $this->actingAs($b)->postJson(self::URL, ['name' => 'X'])->assertCreated();
    }

    // ---------- العرض والتعديل والحذف ----------

    public function test_show_returns_own_service_and_404_for_others_or_missing(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $own = $this->service($mine);
        $foreign = $this->service($other);

        $this->actingAs($mine)->getJson(self::URL.'/'.$own->id)->assertOk()->assertJsonPath('data.id', (string) $own->id);
        $this->actingAs($mine)->getJson(self::URL.'/'.$foreign->id)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->actingAs($mine)->getJson(self::URL.'/999999')->assertStatus(404);
    }

    public function test_update_changes_only_sent_fields(): void
    {
        $user = $this->owner();
        $category = $this->category($user);
        $service = $this->service($user, 'تقصير', 15.0);

        $this->actingAs($user)->patchJson(self::URL.'/'.$service->id, [
            'name' => 'تقصير بنطلون',
            'duration' => 'يوم',
            'item_category_id' => $category->id,
        ])->assertOk()
            ->assertJsonPath('data.name', 'تقصير بنطلون')
            ->assertJsonPath('data.duration', 'يوم')
            ->assertJsonPath('data.price', 15)
            ->assertJsonPath('data.categoryId', (string) $category->id);

        $this->actingAs($user)->patchJson(self::URL.'/'.$service->id, ['item_category_id' => null])
            ->assertOk()->assertJsonPath('data.categoryId', null);
    }

    public function test_update_rejects_empty_body_invalid_data_and_foreign_service(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $own = $this->service($mine);
        $foreign = $this->service($other, 'لغيري');

        $this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        $this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, ['name' => ''])->assertStatus(422);
        $this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, ['price' => -5])->assertStatus(422);
        $this->actingAs($mine)->patchJson(self::URL.'/'.$own->id, ['item_category_id' => $this->category($other)->id])->assertStatus(422);
        $this->actingAs($mine)->patchJson(self::URL.'/'.$foreign->id, ['name' => 'اختراق'])->assertStatus(404);

        $this->assertSame('لغيري', $foreign->refresh()->name);
    }

    public function test_destroy_deletes_own_service_only(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $own = $this->service($mine);
        $foreign = $this->service($other);

        $this->actingAs($mine)->deleteJson(self::URL.'/'.$foreign->id)->assertStatus(404);
        $this->assertDatabaseHas('dashboard_store_services', ['id' => $foreign->id]);

        $this->actingAs($mine)->deleteJson(self::URL.'/'.$own->id)->assertNoContent();
        $this->assertDatabaseMissing('dashboard_store_services', ['id' => $own->id]);
    }

    public function test_deleting_a_category_keeps_the_service_without_category(): void
    {
        $user = $this->owner();
        $category = $this->category($user);
        $service = $this->service($user);
        $service->item_category_id = $category->id;
        $service->save();

        $category->delete();

        $this->assertNull($service->refresh()->item_category_id);
    }
}