<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardItemCategory;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ItemCategoryTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/dashboard/item-categories';

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

    private function owner(string $placeStatus = Place::STATUS_APPROVED, string $plan = 'free'): User
    {
        $n = ++self::$counter;
        $user = UserFactory::new()->storeOwner()->create();

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
        $place->place_status = $placeStatus;
        $place->place_plan = $plan;
        $place->user_id = $user->id;
        $place->save();

        return $user;
    }

    private function placeOf(User $user): Place
    {
        return Place::where('user_id', $user->id)->firstOrFail();
    }

    private function category(User $owner, string $kind = 'menu', string $name = 'مشروبات'): DashboardItemCategory
    {
        $category = new DashboardItemCategory(['kind' => $kind, 'name' => $name]);
        $category->place_id = $this->placeOf($owner)->id;
        $category->save();

        return $category;
    }

    // ---------- الوصول ----------

    public function test_guest_gets_401_on_all_routes(): void
    {
        $this->getJson(self::URL.'?kind=menu')->assertStatus(401)->assertJsonPath('code', 'UNAUTHENTICATED');
        $this->postJson(self::URL, ['kind' => 'menu', 'name' => 'X'])->assertStatus(401);
        $this->getJson(self::URL.'/1')->assertStatus(401);
        $this->patchJson(self::URL.'/1', ['name' => 'X'])->assertStatus(401);
        $this->deleteJson(self::URL.'/1')->assertStatus(401);
    }

    public function test_pending_and_rejected_owner_gets_403(): void
    {
        foreach ([Place::STATUS_PENDING, Place::STATUS_REJECTED] as $status) {
            $user = $this->owner($status);

            $this->actingAs($user)->getJson(self::URL.'?kind=menu')->assertStatus(403)->assertJsonPath('code', 'FORBIDDEN');
            $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => 'X'])->assertStatus(403);
        }
    }

    public function test_plain_user_gets_403(): void
    {
        $plain = $this->owner();
        $plain->user_role = 'user';
        $plain->save();

        $this->actingAs($plain)->getJson(self::URL.'?kind=menu')->assertStatus(403);
    }

    public function test_suspended_owner_is_rejected_immediately(): void
    {
        // active.user يُخرج الموقوف فوراً (401) قبل الوصول للوحة.
        $suspended = $this->owner();
        $suspended->user_status = 'suspended';
        $suspended->save();

        $this->actingAs($suspended)->getJson(self::URL.'?kind=menu')->assertStatus(401);
    }

    // ---------- القائمة ----------

    public function test_index_requires_a_valid_kind(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->getJson(self::URL)->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        $this->actingAs($user)->getJson(self::URL.'?kind=other')->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
    }

    public function test_index_returns_only_own_categories_of_the_requested_kind(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $this->category($mine, 'menu', 'مشروبات');
        $this->category($mine, 'product', 'ملابس');
        $this->category($other, 'menu', 'حلويات');

        $response = $this->actingAs($mine)->getJson(self::URL.'?kind=menu')->assertOk();

        $response->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'مشروبات')
            ->assertJsonPath('data.0.kind', 'menu')
            ->assertJsonPath('meta.limit', 5)
            ->assertJsonPath('meta.used', 1)
            ->assertJsonMissingPath('data.0.place_id');

        $this->assertIsString($response->json('data.0.id'));
    }

    public function test_index_meta_limit_is_null_for_paid_plan(): void
    {
        $user = $this->owner(plan: 'paid');

        $this->actingAs($user)->getJson(self::URL.'?kind=menu')
            ->assertOk()->assertJsonPath('meta.limit', null)->assertJsonPath('meta.used', 0);
    }

    // ---------- الإضافة ----------

    public function test_store_creates_a_category_and_trims_the_name(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => '  مشويات  '])
            ->assertCreated()
            ->assertJsonPath('data.name', 'مشويات')
            ->assertJsonPath('data.kind', 'menu')
            ->assertJsonStructure(['data' => ['id', 'kind', 'name', 'createdAt']]);

        $this->assertDatabaseHas('dashboard_item_categories', [
            'place_id' => $this->placeOf($user)->id,
            'kind' => 'menu',
            'name' => 'مشويات',
        ]);
    }

    public function test_store_ignores_place_id_sent_by_the_client(): void
    {
        $mine = $this->owner();
        $other = $this->owner();

        $this->actingAs($mine)->postJson(self::URL, [
            'kind' => 'menu',
            'name' => 'مشويات',
            'place_id' => $this->placeOf($other)->id,
        ])->assertCreated();

        $this->assertDatabaseHas('dashboard_item_categories', ['place_id' => $this->placeOf($mine)->id, 'name' => 'مشويات']);
        $this->assertDatabaseMissing('dashboard_item_categories', ['place_id' => $this->placeOf($other)->id]);
    }

    public function test_store_validates_input(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->postJson(self::URL, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => '   '])->assertStatus(422);
        $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => str_repeat('ا', 46)])->assertStatus(422);
        $this->actingAs($user)->postJson(self::URL, ['kind' => 'bogus', 'name' => 'X'])->assertStatus(422);
        $this->actingAs($user)->postJson(self::URL, ['name' => 'X'])->assertStatus(422);
        $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => str_repeat('ا', 45)])->assertCreated();

        $this->assertDatabaseCount('dashboard_item_categories', 1);
    }

    public function test_duplicate_name_in_same_place_and_kind_gets_409(): void
    {
        $user = $this->owner();
        $this->category($user, 'menu', 'مشروبات');

        $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => 'مشروبات'])
            ->assertStatus(409)->assertJsonPath('code', 'CATEGORY_EXISTS');

        $this->assertDatabaseCount('dashboard_item_categories', 1);
    }

    public function test_same_name_is_allowed_for_another_kind_or_another_place(): void
    {
        $user = $this->owner();
        $other = $this->owner();
        $this->category($user, 'menu', 'مشروبات');

        $this->actingAs($user)->postJson(self::URL, ['kind' => 'product', 'name' => 'مشروبات'])->assertCreated();
        $this->actingAs($other)->postJson(self::URL, ['kind' => 'menu', 'name' => 'مشروبات'])->assertCreated();
    }

    // ---------- حدود الباقة ----------

    public function test_free_plan_limit_is_enforced_per_kind(): void
    {
        $user = $this->owner();

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => "تصنيف {$i}"])->assertCreated();
        }

        $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => 'تصنيف 6'])
            ->assertStatus(403)->assertJsonPath('code', 'PLAN_LIMIT_REACHED')->assertJsonPath('limit', 5);

        // نوع آخر له عدّاده الخاص.
        $this->actingAs($user)->postJson(self::URL, ['kind' => 'product', 'name' => 'تصنيف 6'])->assertCreated();
        $this->assertSame(5, DashboardItemCategory::where('place_id', $this->placeOf($user)->id)->where('kind', 'menu')->count());
    }

    public function test_paid_plan_has_no_commercial_limit(): void
    {
        $user = $this->owner(plan: 'paid');

        for ($i = 1; $i <= 7; $i++) {
            $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => "تصنيف {$i}"])->assertCreated();
        }

        $this->assertSame(7, DashboardItemCategory::count());
    }

    public function test_hard_cap_applies_even_to_paid_plan(): void
    {
        config(['dashboard_plan.category_hard_cap' => 2]);
        $user = $this->owner(plan: 'paid');

        $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => 'أ'])->assertCreated();
        $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => 'ب'])->assertCreated();
        $this->actingAs($user)->postJson(self::URL, ['kind' => 'menu', 'name' => 'ج'])
            ->assertStatus(403)->assertJsonPath('code', 'PLAN_LIMIT_REACHED')->assertJsonPath('limit', null);
    }

    // ---------- العرض والتعديل والحذف ----------

    public function test_show_returns_own_category(): void
    {
        $user = $this->owner();
        $category = $this->category($user);

        $this->actingAs($user)->getJson(self::URL.'/'.$category->id)
            ->assertOk()->assertJsonPath('data.id', (string) $category->id)->assertJsonPath('data.name', 'مشروبات');
    }

    public function test_other_owners_category_is_404_on_show_update_and_delete(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $theirs = $this->category($other, 'menu', 'سري');

        $this->actingAs($mine)->getJson(self::URL.'/'.$theirs->id)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->actingAs($mine)->patchJson(self::URL.'/'.$theirs->id, ['name' => 'مخترق'])->assertStatus(404);
        $this->actingAs($mine)->deleteJson(self::URL.'/'.$theirs->id)->assertStatus(404);

        $this->assertDatabaseHas('dashboard_item_categories', ['id' => $theirs->id, 'name' => 'سري']);
    }

    public function test_non_numeric_or_missing_id_is_404(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->getJson(self::URL.'/abc')->assertStatus(404);
        $this->actingAs($user)->getJson(self::URL.'/999999')->assertStatus(404);
    }

    public function test_update_changes_only_the_name(): void
    {
        $user = $this->owner();
        $category = $this->category($user, 'menu', 'قديم');

        $this->actingAs($user)->patchJson(self::URL.'/'.$category->id, [
            'name' => '  جديد ',
            'kind' => 'product',
            'place_id' => 999,
        ])->assertOk()->assertJsonPath('data.name', 'جديد')->assertJsonPath('data.kind', 'menu');

        $fresh = $category->fresh();
        $this->assertSame('menu', $fresh->kind);
        $this->assertSame($this->placeOf($user)->id, $fresh->place_id);
    }

    public function test_update_to_an_existing_name_gets_409_and_validates(): void
    {
        $user = $this->owner();
        $this->category($user, 'menu', 'أ');
        $second = $this->category($user, 'menu', 'ب');

        $this->actingAs($user)->patchJson(self::URL.'/'.$second->id, ['name' => 'أ'])
            ->assertStatus(409)->assertJsonPath('code', 'CATEGORY_EXISTS');
        $this->actingAs($user)->patchJson(self::URL.'/'.$second->id, ['name' => ''])->assertStatus(422);
        $this->actingAs($user)->patchJson(self::URL.'/'.$second->id, ['name' => 'ب'])->assertOk();
    }

    public function test_destroy_removes_the_category(): void
    {
        $user = $this->owner();
        $category = $this->category($user);

        $this->actingAs($user)->deleteJson(self::URL.'/'.$category->id)->assertNoContent();

        $this->assertDatabaseMissing('dashboard_item_categories', ['id' => $category->id]);
    }

    public function test_deleting_a_place_deletes_its_categories(): void
    {
        $user = $this->owner();
        $this->category($user);

        $this->placeOf($user)->delete();

        $this->assertDatabaseCount('dashboard_item_categories', 0);
    }
}