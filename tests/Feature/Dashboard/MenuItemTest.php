<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardItemCategory;
use App\Models\DashboardMenuItem;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class MenuItemTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/dashboard/menu-items';

    private static int $counter = 0;

    private Area $area;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');

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

    private function owner(string $type = 'restaurant', string $plan = 'free', string $status = Place::STATUS_APPROVED): User
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
        $place->place_plan = $plan;
        $place->user_id = $user->id;
        $place->save();

        return $user;
    }

    private function placeOf(User $user): Place
    {
        return Place::where('user_id', $user->id)->firstOrFail();
    }

    private function category(User $owner, string $kind = 'menu'): DashboardItemCategory
    {
        $category = new DashboardItemCategory(['kind' => $kind, 'name' => 'تصنيف '.Str::random(4)]);
        $category->place_id = $this->placeOf($owner)->id;
        $category->save();

        return $category;
    }

    private function item(User $owner, string $name = 'مسقعة'): DashboardMenuItem
    {
        $item = new DashboardMenuItem(['name' => $name, 'price' => 12.5]);
        $item->place_id = $this->placeOf($owner)->id;
        $item->save();

        return $item;
    }

    // ---------- الوصول ----------

    public function test_guest_gets_401_on_all_routes(): void
    {
        $this->getJson(self::URL)->assertStatus(401);
        $this->postJson(self::URL, ['name' => 'X'])->assertStatus(401);
        $this->getJson(self::URL.'/1')->assertStatus(401);
        $this->patchJson(self::URL.'/1', ['name' => 'X'])->assertStatus(401);
        $this->deleteJson(self::URL.'/1')->assertStatus(401);
        $this->postJson(self::URL.'/1/image')->assertStatus(401);
        $this->deleteJson(self::URL.'/1/image')->assertStatus(401);
    }

    public function test_pending_owner_and_plain_user_get_403(): void
    {
        $pending = $this->owner(status: Place::STATUS_PENDING);
        $this->actingAs($pending)->getJson(self::URL)->assertStatus(403);

        $plain = $this->owner();
        $plain->user_role = 'user';
        $plain->save();
        $this->actingAs($plain)->postJson(self::URL, ['name' => 'X'])->assertStatus(403);
    }

    public function test_store_and_workspace_places_have_no_menu(): void
    {
        foreach (['store', 'workspace'] as $type) {
            $user = $this->owner($type);

            $this->actingAs($user)->getJson(self::URL)->assertStatus(403)->assertJsonPath('code', 'MENU_NOT_SUPPORTED');
            $this->actingAs($user)->postJson(self::URL, ['name' => 'X'])->assertStatus(403);
        }

        $this->assertSame(0, DashboardMenuItem::count());
    }

    public function test_all_food_place_types_are_allowed(): void
    {
        foreach (['restaurant', 'cafe', 'restaurant-cafe'] as $type) {
            $this->actingAs($this->owner($type))->postJson(self::URL, ['name' => 'X'])->assertCreated();
        }
    }

    // ---------- القائمة ----------

    public function test_index_returns_only_own_items_newest_first(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $this->item($mine, 'قديم');
        $this->item($mine, 'جديد');
        $this->item($other, 'لغيري');

        $response = $this->actingAs($mine)->getJson(self::URL)->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'جديد')
            ->assertJsonPath('data.1.name', 'قديم')
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.used', 2)
            ->assertJsonMissingPath('data.0.place_id');

        $this->assertIsString($response->json('data.0.id'));
    }

    public function test_index_meta_limit_is_null_for_paid_plan(): void
    {
        $this->actingAs($this->owner(plan: 'paid'))->getJson(self::URL)
            ->assertOk()->assertJsonPath('meta.limit', null);
    }

    // ---------- الإضافة ----------

    public function test_store_creates_an_item_and_trims_the_name(): void
    {
        $user = $this->owner();
        $category = $this->category($user);

        $this->actingAs($user)->postJson(self::URL, [
            'name' => '  مسقعة باللحمة  ',
            'price' => 18.5,
            'is_available' => false,
            'item_category_id' => $category->id,
        ])->assertCreated()
            ->assertJsonPath('data.name', 'مسقعة باللحمة')
            ->assertJsonPath('data.price', 18.5)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.categoryId', (string) $category->id)
            ->assertJsonPath('data.image', null)
            ->assertJsonStructure(['data' => ['id', 'name', 'price', 'available', 'categoryId', 'image', 'createdAt']]);

        $this->assertDatabaseHas('dashboard_menu_items', [
            'place_id' => $this->placeOf($user)->id,
            'name' => 'مسقعة باللحمة',
            'is_available' => 0,
        ]);
    }

    public function test_store_allows_no_price_and_no_category_and_defaults_to_available(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->postJson(self::URL, ['name' => 'شاي', 'price' => ''])
            ->assertCreated()
            ->assertJsonPath('data.price', null)
            ->assertJsonPath('data.categoryId', null)
            ->assertJsonPath('data.available', true);
    }

    public function test_store_ignores_place_id_and_image_sent_by_the_client(): void
    {
        $mine = $this->owner();
        $other = $this->owner();

        $this->actingAs($mine)->postJson(self::URL, [
            'name' => 'شاي',
            'place_id' => $this->placeOf($other)->id,
            'image' => 'places/hacked.jpg',
        ])->assertCreated()->assertJsonPath('data.image', null);

        $this->assertSame(1, DashboardMenuItem::where('place_id', $this->placeOf($mine)->id)->count());
        $this->assertSame(0, DashboardMenuItem::where('place_id', $this->placeOf($other)->id)->count());
        $this->assertNull(DashboardMenuItem::first()->image);
    }

    public function test_store_validates_input(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->postJson(self::URL, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        $this->actingAs($user)->postJson(self::URL, ['name' => '   '])->assertStatus(422);
        $this->actingAs($user)->postJson(self::URL, ['name' => str_repeat('ا', 81)])->assertStatus(422);
        $this->actingAs($user)->postJson(self::URL, ['name' => 'X', 'price' => -1])->assertStatus(422);
        $this->actingAs($user)->postJson(self::URL, ['name' => 'X', 'price' => 'abc'])->assertStatus(422);
        $this->actingAs($user)->postJson(self::URL, ['name' => 'X', 'price' => 1000000])->assertStatus(422);
        $this->actingAs($user)->postJson(self::URL, ['name' => 'X', 'price' => 1.234])->assertStatus(422);
        $this->actingAs($user)->postJson(self::URL, ['name' => 'X', 'is_available' => 'maybe'])->assertStatus(422);

        $this->assertSame(0, DashboardMenuItem::count());
    }

    public function test_store_rejects_foreign_or_wrong_kind_category(): void
    {
        $mine = $this->owner();
        $other = $this->owner();

        foreach ([$this->category($other), $this->category($mine, 'product'), 99999] as $category) {
            $id = $category instanceof DashboardItemCategory ? $category->id : $category;

            $this->actingAs($mine)->postJson(self::URL, ['name' => 'X', 'item_category_id' => $id])
                ->assertStatus(422)->assertJsonStructure(['errors' => ['item_category_id']]);
        }

        $this->assertSame(0, DashboardMenuItem::count());
    }

    public function test_free_plan_limit_returns_403_then_paid_is_unlimited(): void
    {
        $free = $this->owner();

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($free)->postJson(self::URL, ['name' => "صنف $i"])->assertCreated();
        }

        $this->actingAs($free)->postJson(self::URL, ['name' => 'الحادي عشر'])
            ->assertStatus(403)->assertJsonPath('code', 'PLAN_LIMIT_REACHED')->assertJsonPath('limit', 10);

        $this->assertSame(10, DashboardMenuItem::where('place_id', $this->placeOf($free)->id)->count());

        $paid = $this->owner(plan: 'paid');

        for ($i = 1; $i <= 11; $i++) {
            $this->actingAs($paid)->postJson(self::URL, ['name' => "صنف $i"])->assertCreated();
        }
    }

    public function test_limit_is_per_place(): void
    {
        $a = $this->owner();
        $b = $this->owner();

        for ($i = 1; $i <= 10; $i++) {
            $this->item($a, "صنف $i");
        }

        $this->actingAs($b)->postJson(self::URL, ['name' => 'X'])->assertCreated();
    }

    // ---------- العرض والتعديل والحذف ----------

    public function test_show_update_and_destroy_on_own_item(): void
    {
        $user = $this->owner();
        $item = $this->item($user);
        $category = $this->category($user);

        $this->actingAs($user)->getJson(self::URL.'/'.$item->id)->assertOk()->assertJsonPath('data.name', 'مسقعة');

        $this->actingAs($user)->patchJson(self::URL.'/'.$item->id, [
            'name' => ' مسقعة جديدة ',
            'price' => null,
            'is_available' => false,
            'item_category_id' => $category->id,
        ])->assertOk()
            ->assertJsonPath('data.name', 'مسقعة جديدة')
            ->assertJsonPath('data.price', null)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.categoryId', (string) $category->id);

        $this->actingAs($user)->deleteJson(self::URL.'/'.$item->id)->assertNoContent();
        $this->assertDatabaseMissing('dashboard_menu_items', ['id' => $item->id]);
    }

    public function test_partial_update_keeps_other_fields_and_empty_body_is_422(): void
    {
        $user = $this->owner();
        $item = $this->item($user);

        $this->actingAs($user)->patchJson(self::URL.'/'.$item->id, ['is_available' => false])
            ->assertOk()->assertJsonPath('data.name', 'مسقعة')->assertJsonPath('data.price', 12.5);

        $this->actingAs($user)->patchJson(self::URL.'/'.$item->id, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        $this->actingAs($user)->patchJson(self::URL.'/'.$item->id, ['name' => ''])->assertStatus(422);
    }

    public function test_update_cannot_move_an_item_to_another_place_or_foreign_category(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $item = $this->item($mine);

        $this->actingAs($mine)->patchJson(self::URL.'/'.$item->id, ['place_id' => $this->placeOf($other)->id, 'name' => 'Y'])
            ->assertOk();
        $this->assertSame($this->placeOf($mine)->id, $item->refresh()->place_id);

        $this->actingAs($mine)->patchJson(self::URL.'/'.$item->id, ['item_category_id' => $this->category($other)->id])
            ->assertStatus(422);
    }

    public function test_other_owners_item_is_404_everywhere(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $item = $this->item($other);
        $url = self::URL.'/'.$item->id;

        $this->actingAs($mine)->getJson($url)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->actingAs($mine)->patchJson($url, ['name' => 'Y'])->assertStatus(404);
        $this->actingAs($mine)->deleteJson($url)->assertStatus(404);
        $this->actingAs($mine)->post($url.'/image', ['image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertStatus(404);
        $this->actingAs($mine)->deleteJson($url.'/image')->assertStatus(404);

        $this->assertDatabaseHas('dashboard_menu_items', ['id' => $item->id, 'name' => 'مسقعة']);
    }

    public function test_deleting_a_category_keeps_items_without_category(): void
    {
        $user = $this->owner();
        $category = $this->category($user);
        $item = $this->item($user);
        $item->item_category_id = $category->id;
        $item->save();

        $category->delete();

        $this->assertNull($item->refresh()->item_category_id);
    }

    // ---------- الصورة ----------

    public function test_image_upload_stores_file_and_replaces_the_old_one(): void
    {
        $user = $this->owner();
        $item = $this->item($user);
        $url = self::URL.'/'.$item->id.'/image';

        $first = $this->actingAs($user)->post($url, ['image' => UploadedFile::fake()->image('a.png', 100, 100)], ['Accept' => 'application/json']);
        $first->assertOk();
        $this->assertNotNull($first->json('data.image'));

        $oldPath = $item->refresh()->image;
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($user)->post($url, ['image' => UploadedFile::fake()->image('b.webp', 100, 100)], ['Accept' => 'application/json'])->assertOk();
        $newPath = $item->refresh()->image;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_image_rejects_bad_files(): void
    {
        $user = $this->owner();
        $item = $this->item($user);
        $url = self::URL.'/'.$item->id.'/image';

        foreach ([
            UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('big.png', 6000, 'image/png'),
        ] as $file) {
            $this->actingAs($user)->post($url, ['image' => $file], ['Accept' => 'application/json'])
                ->assertStatus(422)->assertJsonPath('code', 'VALIDATION')->assertJsonStructure(['errors' => ['image']]);
        }

        $this->actingAs($user)->post($url, [], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertNull($item->refresh()->image);
    }

    public function test_image_delete_and_item_delete_remove_the_file(): void
    {
        $user = $this->owner();
        $item = $this->item($user);
        $url = self::URL.'/'.$item->id;

        $this->actingAs($user)->post($url.'/image', ['image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertOk();
        $path = $item->refresh()->image;

        $this->actingAs($user)->deleteJson($url.'/image')->assertOk()->assertJsonPath('data.image', null);
        Storage::disk('public')->assertMissing($path);

        $this->actingAs($user)->post($url.'/image', ['image' => UploadedFile::fake()->image('b.png')], ['Accept' => 'application/json'])->assertOk();
        $path2 = $item->refresh()->image;

        $this->actingAs($user)->deleteJson($url)->assertNoContent();
        Storage::disk('public')->assertMissing($path2);
    }
}