<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardItemCategory;
use App\Models\DashboardStoreProduct;
use App\Models\Place;
use App\Models\Regions;
use App\Models\StoreCategory;
use App\Models\StoreCategoryGroup;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class StoreProductTest extends TestCase
{
    use RefreshDatabase;

    private const URL = '/api/dashboard/store-products';

    private static int $counter = 0;

    private Area $area;

    private StoreCategoryGroup $group;

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

        $this->group = new StoreCategoryGroup;
        $this->group->forceFill([
            'store_category_group_slug' => 'g-'.Str::random(6),
            'store_category_group_name_en' => 'Group',
            'store_category_group_name_ar' => 'مجموعة',
        ])->save();
    }

    /** $variant = null يعني محلاً بلا تصنيف متجر (يُعامل كـ general). */
    private function owner(?string $variant = 'general', string $plan = 'free', string $type = 'store', string $status = Place::STATUS_APPROVED): User
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

    private function category(User $owner, string $kind = 'product'): DashboardItemCategory
    {
        $category = new DashboardItemCategory(['kind' => $kind, 'name' => 'تصنيف '.Str::random(4)]);
        $category->place_id = $this->placeOf($owner)->id;
        $category->save();

        return $category;
    }

    private function product(User $owner, string $name = 'أرز', bool $discount = false): DashboardStoreProduct
    {
        $product = new DashboardStoreProduct(['name' => $name, 'price' => 12.5, 'has_discount' => $discount]);
        $product->place_id = $this->placeOf($owner)->id;
        $product->save();

        return $product;
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
        $this->actingAs($this->owner(status: Place::STATUS_PENDING))->getJson(self::URL)->assertStatus(403);

        $plain = $this->owner();
        $plain->user_role = 'user';
        $plain->save();
        $this->actingAs($plain)->postJson(self::URL, ['name' => 'X'])->assertStatus(403);
    }

    public function test_non_store_places_have_no_products(): void
    {
        foreach (['cafe', 'restaurant', 'restaurant-cafe', 'workspace'] as $type) {
            $user = $this->owner('general', 'free', $type);

            $this->actingAs($user)->getJson(self::URL)->assertStatus(403)->assertJsonPath('code', 'STORE_PRODUCTS_NOT_SUPPORTED');
            $this->actingAs($user)->postJson(self::URL, ['name' => 'X'])->assertStatus(403);
        }

        $this->assertSame(0, DashboardStoreProduct::count());
    }

    public function test_service_variants_have_no_products(): void
    {
        foreach (['tailoring', 'clinic', 'repair', 'salonmen', 'salonwomen', 'laundry', 'photography', 'garage'] as $variant) {
            $user = $this->owner($variant);

            $this->actingAs($user)->getJson(self::URL)->assertStatus(403)->assertJsonPath('code', 'STORE_PRODUCTS_NOT_SUPPORTED');
            $this->actingAs($user)->postJson(self::URL, ['name' => 'X'])->assertStatus(403);
        }

        $this->assertSame(0, DashboardStoreProduct::count());
    }

    public function test_store_without_category_is_treated_as_general(): void
    {
        $this->actingAs($this->owner(null))->postJson(self::URL, ['name' => 'X'])->assertCreated();
    }

    // ---------- القائمة ----------

    public function test_index_returns_only_own_products_newest_first_with_meta(): void
    {
        $mine = $this->owner('menswear');
        $other = $this->owner('menswear');
        $this->product($mine, 'قديم', true);
        $this->product($mine, 'جديد');
        $this->product($other, 'لغيري');

        $response = $this->actingAs($mine)->getJson(self::URL)->assertOk();

        $response->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'جديد')
            ->assertJsonPath('data.1.name', 'قديم')
            ->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.used', 2)
            ->assertJsonPath('meta.discountLimit', 5)
            ->assertJsonPath('meta.discountUsed', 1)
            ->assertJsonPath('meta.supportsDiscount', true)
            ->assertJsonMissingPath('data.0.place_id');

        $this->assertIsString($response->json('data.0.id'));
    }

    public function test_index_meta_for_paid_and_for_variant_without_discount(): void
    {
        $this->actingAs($this->owner('menswear', 'paid'))->getJson(self::URL)
            ->assertOk()->assertJsonPath('meta.limit', null)->assertJsonPath('meta.discountLimit', null)
            ->assertJsonPath('meta.supportsDiscount', true);

        $this->actingAs($this->owner('grocery'))->getJson(self::URL)
            ->assertOk()->assertJsonPath('meta.limit', 15)->assertJsonPath('meta.supportsDiscount', false);
    }

    // ---------- الإضافة ----------

    public function test_store_creates_a_product_with_all_fields(): void
    {
        $user = $this->owner('womenswear');
        $category = $this->category($user);

        $this->actingAs($user)->postJson(self::URL, [
            'name' => '  فستان سهرة  ',
            'price' => 150.5,
            'is_available' => false,
            'has_discount' => true,
            'item_category_id' => $category->id,
            'details' => 'قماش شيفون',
            'brand' => 'ماركة',
            'size' => 'M',
            'color' => 'أسود',
            'material' => 'شيفون',
            'extra' => ['unit' => 'قطعة', 'expiry' => '', 'bogus' => 'x'],
        ])->assertCreated()
            ->assertJsonPath('data.name', 'فستان سهرة')
            ->assertJsonPath('data.price', 150.5)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.discount', true)
            ->assertJsonPath('data.categoryId', (string) $category->id)
            ->assertJsonPath('data.details', 'قماش شيفون')
            ->assertJsonPath('data.size', 'M')
            ->assertJsonPath('data.extra', ['unit' => 'قطعة'])
            ->assertJsonPath('data.image', null)
            ->assertJsonStructure(['data' => ['id', 'name', 'price', 'available', 'discount', 'categoryId', 'details', 'brand', 'size', 'color', 'material', 'extra', 'image', 'createdAt']]);

        $this->assertDatabaseHas('dashboard_store_products', [
            'place_id' => $this->placeOf($user)->id,
            'name' => 'فستان سهرة',
            'is_available' => 0,
            'has_discount' => 1,
        ]);
    }

    public function test_store_allows_minimal_product_with_defaults(): void
    {
        $user = $this->owner();

        $this->actingAs($user)->postJson(self::URL, ['name' => 'سكر', 'price' => '', 'details' => '  ', 'extra' => []])
            ->assertCreated()
            ->assertJsonPath('data.price', null)
            ->assertJsonPath('data.categoryId', null)
            ->assertJsonPath('data.details', null)
            ->assertJsonPath('data.extra', null)
            ->assertJsonPath('data.available', true)
            ->assertJsonPath('data.discount', false);
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

        $this->assertSame(1, DashboardStoreProduct::where('place_id', $this->placeOf($mine)->id)->count());
        $this->assertSame(0, DashboardStoreProduct::where('place_id', $this->placeOf($other)->id)->count());
        $this->assertNull(DashboardStoreProduct::first()->image);
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
            ['name' => 'X', 'is_available' => 'maybe'],
            ['name' => 'X', 'has_discount' => 'maybe'],
            ['name' => 'X', 'details' => str_repeat('ا', 301)],
            ['name' => 'X', 'brand' => str_repeat('ا', 41)],
            ['name' => 'X', 'size' => str_repeat('1', 21)],
            ['name' => 'X', 'color' => str_repeat('ا', 41)],
            ['name' => 'X', 'material' => str_repeat('ا', 41)],
            ['name' => 'X', 'extra' => 'text'],
            ['name' => 'X', 'extra' => ['expiry' => str_repeat('1', 21)]],
        ];

        foreach ($bad as $payload) {
            $this->actingAs($user)->postJson(self::URL, $payload)->assertStatus(422);
        }

        $this->assertSame(0, DashboardStoreProduct::count());
    }

    public function test_store_rejects_foreign_or_wrong_kind_category(): void
    {
        $mine = $this->owner();
        $other = $this->owner();

        foreach ([$this->category($other), $this->category($mine, 'menu'), 99999] as $category) {
            $id = $category instanceof DashboardItemCategory ? $category->id : $category;

            $this->actingAs($mine)->postJson(self::URL, ['name' => 'X', 'item_category_id' => $id])
                ->assertStatus(422)->assertJsonStructure(['errors' => ['item_category_id']]);
        }

        $this->assertSame(0, DashboardStoreProduct::count());
    }

    // ---------- حدود الباقة ----------

    public function test_free_product_limit_depends_on_variant_and_paid_is_unlimited(): void
    {
        $general = $this->owner('general');

        for ($i = 1; $i <= 10; $i++) {
            $this->actingAs($general)->postJson(self::URL, ['name' => "منتج $i"])->assertCreated();
        }

        $this->actingAs($general)->postJson(self::URL, ['name' => 'الحادي عشر'])
            ->assertStatus(403)->assertJsonPath('code', 'PLAN_LIMIT_REACHED')->assertJsonPath('limit', 10);

        $grocery = $this->owner('grocery');

        for ($i = 1; $i <= 15; $i++) {
            $this->actingAs($grocery)->postJson(self::URL, ['name' => "منتج $i"])->assertCreated();
        }

        $this->actingAs($grocery)->postJson(self::URL, ['name' => 'السادس عشر'])
            ->assertStatus(403)->assertJsonPath('limit', 15);

        $paid = $this->owner('general', 'paid');

        for ($i = 1; $i <= 16; $i++) {
            $this->actingAs($paid)->postJson(self::URL, ['name' => "منتج $i"])->assertCreated();
        }
    }

    public function test_limit_is_per_place(): void
    {
        $a = $this->owner();
        $b = $this->owner();

        for ($i = 1; $i <= 10; $i++) {
            $this->product($a, "منتج $i");
        }

        $this->actingAs($b)->postJson(self::URL, ['name' => 'X'])->assertCreated();
    }

    public function test_discount_is_rejected_for_variants_without_discounts(): void
    {
        $user = $this->owner('grocery');

        $this->actingAs($user)->postJson(self::URL, ['name' => 'X', 'has_discount' => true])
            ->assertStatus(403)->assertJsonPath('code', 'DISCOUNT_NOT_SUPPORTED');

        $this->actingAs($user)->postJson(self::URL, ['name' => 'X', 'has_discount' => false])->assertCreated();

        $product = DashboardStoreProduct::first();
        $this->actingAs($user)->patchJson(self::URL.'/'.$product->id, ['has_discount' => true])
            ->assertStatus(403)->assertJsonPath('code', 'DISCOUNT_NOT_SUPPORTED');
        $this->assertFalse($product->refresh()->has_discount);
    }

    public function test_free_discount_limit_is_5_and_paid_is_unlimited(): void
    {
        $free = $this->owner('menswear');

        for ($i = 1; $i <= 5; $i++) {
            $this->actingAs($free)->postJson(self::URL, ['name' => "منتج $i", 'has_discount' => true])->assertCreated();
        }

        $this->actingAs($free)->postJson(self::URL, ['name' => 'سادس', 'has_discount' => true])
            ->assertStatus(403)->assertJsonPath('code', 'DISCOUNT_LIMIT_REACHED')->assertJsonPath('limit', 5);

        // منتج بلا خصم ما زال مقبولاً.
        $this->actingAs($free)->postJson(self::URL, ['name' => 'سادس', 'has_discount' => false])->assertCreated();

        $paid = $this->owner('menswear', 'paid');

        for ($i = 1; $i <= 6; $i++) {
            $this->actingAs($paid)->postJson(self::URL, ['name' => "منتج $i", 'has_discount' => true])->assertCreated();
        }
    }

    public function test_enabling_discount_on_update_respects_the_limit(): void
    {
        $user = $this->owner('shoes');

        for ($i = 1; $i <= 5; $i++) {
            $this->product($user, "مخفّض $i", true);
        }

        $plain = $this->product($user, 'عادي');

        $this->actingAs($user)->patchJson(self::URL.'/'.$plain->id, ['has_discount' => true])
            ->assertStatus(403)->assertJsonPath('code', 'DISCOUNT_LIMIT_REACHED');
        $this->assertFalse($plain->refresh()->has_discount);

        // تعديل منتج مخفّض أصلاً لا يُحتسب ضد الحد، ويمكن إلغاء الخصم ثم تفعيله منتجاً آخر.
        $discounted = DashboardStoreProduct::where('has_discount', true)->first();
        $this->actingAs($user)->patchJson(self::URL.'/'.$discounted->id, ['name' => 'اسم جديد', 'has_discount' => true])->assertOk();
        $this->actingAs($user)->patchJson(self::URL.'/'.$discounted->id, ['has_discount' => false])->assertOk();
        $this->actingAs($user)->patchJson(self::URL.'/'.$plain->id, ['has_discount' => true])->assertOk();
    }

    // ---------- العرض والتعديل والحذف ----------

    public function test_show_update_and_destroy_on_own_product(): void
    {
        $user = $this->owner();
        $product = $this->product($user);
        $category = $this->category($user);

        $this->actingAs($user)->getJson(self::URL.'/'.$product->id)->assertOk()->assertJsonPath('data.name', 'أرز');

        $this->actingAs($user)->patchJson(self::URL.'/'.$product->id, [
            'name' => ' أرز بسمتي ',
            'price' => null,
            'is_available' => false,
            'item_category_id' => $category->id,
            'brand' => 'ماركة',
            'extra' => ['unit' => 'كيلو', 'origin' => 'مستورد'],
        ])->assertOk()
            ->assertJsonPath('data.name', 'أرز بسمتي')
            ->assertJsonPath('data.price', null)
            ->assertJsonPath('data.available', false)
            ->assertJsonPath('data.brand', 'ماركة')
            ->assertJsonPath('data.extra', ['unit' => 'كيلو', 'origin' => 'مستورد'])
            ->assertJsonPath('data.categoryId', (string) $category->id);

        $this->actingAs($user)->deleteJson(self::URL.'/'.$product->id)->assertNoContent();
        $this->assertDatabaseMissing('dashboard_store_products', ['id' => $product->id]);
    }

    public function test_partial_update_keeps_other_fields_and_empty_body_is_422(): void
    {
        $user = $this->owner();
        $product = $this->product($user);

        $this->actingAs($user)->patchJson(self::URL.'/'.$product->id, ['is_available' => false])
            ->assertOk()->assertJsonPath('data.name', 'أرز')->assertJsonPath('data.price', 12.5);

        $this->actingAs($user)->patchJson(self::URL.'/'.$product->id, ['brand' => ''])
            ->assertOk()->assertJsonPath('data.brand', null);

        $this->actingAs($user)->patchJson(self::URL.'/'.$product->id, [])->assertStatus(422)->assertJsonPath('code', 'VALIDATION');
        $this->actingAs($user)->patchJson(self::URL.'/'.$product->id, ['name' => ''])->assertStatus(422);
    }

    public function test_update_cannot_move_a_product_to_another_place_or_foreign_category(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $product = $this->product($mine);

        $this->actingAs($mine)->patchJson(self::URL.'/'.$product->id, ['place_id' => $this->placeOf($other)->id, 'name' => 'Y'])->assertOk();
        $this->assertSame($this->placeOf($mine)->id, $product->refresh()->place_id);

        $this->actingAs($mine)->patchJson(self::URL.'/'.$product->id, ['item_category_id' => $this->category($other)->id])
            ->assertStatus(422);
    }

    public function test_other_owners_product_is_404_everywhere(): void
    {
        $mine = $this->owner();
        $other = $this->owner();
        $product = $this->product($other);
        $url = self::URL.'/'.$product->id;

        $this->actingAs($mine)->getJson($url)->assertStatus(404)->assertJsonPath('code', 'NOT_FOUND');
        $this->actingAs($mine)->patchJson($url, ['name' => 'Y'])->assertStatus(404);
        $this->actingAs($mine)->deleteJson($url)->assertStatus(404);
        $this->actingAs($mine)->post($url.'/image', ['image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertStatus(404);
        $this->actingAs($mine)->deleteJson($url.'/image')->assertStatus(404);

        $this->assertDatabaseHas('dashboard_store_products', ['id' => $product->id, 'name' => 'أرز']);
    }

    public function test_deleting_a_category_keeps_products_without_category(): void
    {
        $user = $this->owner();
        $category = $this->category($user);
        $product = $this->product($user);
        $product->item_category_id = $category->id;
        $product->save();

        $category->delete();

        $this->assertNull($product->refresh()->item_category_id);
    }

    // ---------- الصورة ----------

    public function test_image_upload_stores_file_and_replaces_the_old_one(): void
    {
        $user = $this->owner();
        $product = $this->product($user);
        $url = self::URL.'/'.$product->id.'/image';

        $first = $this->actingAs($user)->post($url, ['image' => UploadedFile::fake()->image('a.png', 100, 100)], ['Accept' => 'application/json']);
        $first->assertOk();
        $this->assertNotNull($first->json('data.image'));

        $oldPath = $product->refresh()->image;
        Storage::disk('public')->assertExists($oldPath);

        $this->actingAs($user)->post($url, ['image' => UploadedFile::fake()->image('b.webp', 100, 100)], ['Accept' => 'application/json'])->assertOk();
        $newPath = $product->refresh()->image;

        $this->assertNotSame($oldPath, $newPath);
        Storage::disk('public')->assertExists($newPath);
        Storage::disk('public')->assertMissing($oldPath);
    }

    public function test_image_rejects_bad_files(): void
    {
        $user = $this->owner();
        $product = $this->product($user);
        $url = self::URL.'/'.$product->id.'/image';

        foreach ([
            UploadedFile::fake()->create('doc.pdf', 100, 'application/pdf'),
            UploadedFile::fake()->create('big.png', 6000, 'image/png'),
        ] as $file) {
            $this->actingAs($user)->post($url, ['image' => $file], ['Accept' => 'application/json'])
                ->assertStatus(422)->assertJsonPath('code', 'VALIDATION')->assertJsonStructure(['errors' => ['image']]);
        }

        $this->actingAs($user)->post($url, [], ['Accept' => 'application/json'])->assertStatus(422);
        $this->assertNull($product->refresh()->image);
    }

    public function test_image_delete_and_product_delete_remove_the_file(): void
    {
        $user = $this->owner();
        $product = $this->product($user);
        $url = self::URL.'/'.$product->id;

        $this->actingAs($user)->post($url.'/image', ['image' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])->assertOk();
        $path = $product->refresh()->image;

        $this->actingAs($user)->deleteJson($url.'/image')->assertOk()->assertJsonPath('data.image', null);
        Storage::disk('public')->assertMissing($path);

        $this->actingAs($user)->post($url.'/image', ['image' => UploadedFile::fake()->image('b.png')], ['Accept' => 'application/json'])->assertOk();
        $path2 = $product->refresh()->image;

        $this->actingAs($user)->deleteJson($url)->assertNoContent();
        Storage::disk('public')->assertMissing($path2);
    }
}