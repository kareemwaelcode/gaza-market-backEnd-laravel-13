<?php

namespace Tests\Feature\Dashboard;

use App\Models\Area;
use App\Models\DashboardAd;
use App\Models\DashboardItemCategory;
use App\Models\DashboardStoreProduct;
use App\Models\DashboardStoreService;
use App\Models\DashboardTable;
use App\Models\Place;
use App\Models\Regions;
use App\Models\StoreCategory;
use App\Models\StoreCategoryGroup;
use App\Models\User;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * علامة "فوق حد الباقة" للتصنيفات والمنتجات والخدمات والطاولات والإعلانات بعد انتهاء الباقة.
 * القاعدة: أقدم N عنصر تبقى، والباقي overLimit، بدون حذف، والتجديد يرجّع كل شي.
 * (أصناف المنيو لها ملف اختبار مستقل: MenuItemOverLimitTest.)
 */
class PlanOverflowTest extends TestCase
{
    use RefreshDatabase;

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

    /** $expiresAt ماضي = باقة انتهت (place_plan يرجع free تلقائياً). */
    private function owner(string $type = 'cafe', ?string $variant = null, string $plan = 'paid', $expiresAt = null): User
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
        $place->place_status = Place::STATUS_APPROVED;
        $place->place_plan = $plan;
        $place->place_plan_expires_at = $expiresAt;
        $place->user_id = $user->id;
        $place->save();

        return $user;
    }

    private function placeOf(User $user): Place
    {
        return Place::where('user_id', $user->id)->firstOrFail();
    }

    private function renew(User $user): void
    {
        $this->placeOf($user)->forceFill(['place_plan_expires_at' => now()->addMonth()])->save();
    }

    /** @return array<string, bool> id => overLimit */
    private function flags(array $response): array
    {
        return collect($response['data'])->pluck('overLimit', 'id')->all();
    }

    // ───────── التصنيفات (حد لكل kind: menu 5 / product 8 / service 8) ─────────

    public function test_categories_are_limited_per_kind_and_keep_the_oldest(): void
    {
        $user = $this->owner('cafe', null, 'paid', now()->subDay());
        $place = $this->placeOf($user);

        $menu = [];
        for ($i = 1; $i <= 7; $i++) {
            $c = new DashboardItemCategory(['kind' => 'menu', 'name' => "قائمة $i"]);
            $c->place_id = $place->id;
            $c->save();
            $menu[] = $c;
        }

        for ($i = 1; $i <= 9; $i++) {
            $c = new DashboardItemCategory(['kind' => 'product', 'name' => "منتج $i"]);
            $c->place_id = $place->id;
            $c->save();
        }

        $menuResponse = $this->actingAs($user)->getJson('/api/dashboard/item-categories?kind=menu')->assertOk();
        $menuResponse->assertJsonPath('meta.limit', 5)->assertJsonPath('meta.overLimitCount', 2);

        $flags = $this->flags($menuResponse->json());
        foreach (array_slice($menu, 0, 5) as $c) {
            $this->assertFalse($flags[(string) $c->id]);
        }
        foreach (array_slice($menu, 5) as $c) {
            $this->assertTrue($flags[(string) $c->id]);
        }

        // الحد منفصل لكل نوع: 9 تصنيفات منتجات فوق حد 8 → واحد فقط.
        $this->actingAs($user)->getJson('/api/dashboard/item-categories?kind=product')
            ->assertOk()->assertJsonPath('meta.limit', 8)->assertJsonPath('meta.overLimitCount', 1);

        $this->assertSame(16, DashboardItemCategory::count());
    }

    public function test_category_flag_follows_on_show_and_renewal_clears_it(): void
    {
        $user = $this->owner('cafe', null, 'paid', now()->subDay());
        $place = $this->placeOf($user);

        $last = null;
        for ($i = 1; $i <= 6; $i++) {
            $last = new DashboardItemCategory(['kind' => 'menu', 'name' => "قائمة $i"]);
            $last->place_id = $place->id;
            $last->save();
        }

        $this->actingAs($user)->getJson('/api/dashboard/item-categories/'.$last->id)
            ->assertOk()->assertJsonPath('data.overLimit', true);

        $this->renew($user);

        $this->actingAs($user)->getJson('/api/dashboard/item-categories/'.$last->id)
            ->assertOk()->assertJsonPath('data.overLimit', false);
        $this->actingAs($user)->getJson('/api/dashboard/item-categories?kind=menu')
            ->assertJsonPath('meta.overLimitCount', 0);
    }

    // ───────── الخدمات (حد 10) ─────────

    public function test_services_over_the_free_limit_are_flagged_and_renewal_restores(): void
    {
        $user = $this->owner('store', 'repair', 'paid', now()->subDay());
        $place = $this->placeOf($user);

        $services = [];
        for ($i = 1; $i <= 12; $i++) {
            $s = new DashboardStoreService(['name' => "خدمة $i"]);
            $s->place_id = $place->id;
            $s->save();
            $services[] = $s;
        }

        $response = $this->actingAs($user)->getJson('/api/dashboard/store-services')->assertOk();
        $response->assertJsonPath('meta.limit', 10)->assertJsonPath('meta.overLimitCount', 2);

        $flags = $this->flags($response->json());
        $this->assertFalse($flags[(string) $services[9]->id]);
        $this->assertTrue($flags[(string) $services[10]->id]);
        $this->assertTrue($flags[(string) $services[11]->id]);

        $this->actingAs($user)->getJson('/api/dashboard/store-services/'.$services[11]->id)
            ->assertOk()->assertJsonPath('data.overLimit', true);

        $this->renew($user);

        $this->actingAs($user)->getJson('/api/dashboard/store-services')
            ->assertJsonPath('meta.overLimitCount', 0);
        $this->assertSame(12, DashboardStoreService::count());
    }

    // ───────── الطاولات (حد 5) ─────────

    public function test_tables_over_the_free_limit_are_flagged_and_hidden_meta_matches(): void
    {
        $user = $this->owner('cafe', null, 'paid', now()->subDay());
        $place = $this->placeOf($user);

        $tables = [];
        for ($i = 1; $i <= 7; $i++) {
            $t = new DashboardTable(['name' => "طاولة $i", 'seats' => 4, 'status' => 'available']);
            $t->place_id = $place->id;
            $t->save();
            $tables[] = $t;
        }

        $response = $this->actingAs($user)->getJson('/api/dashboard/tables')->assertOk();
        $response->assertJsonPath('meta.limit', 5)
            ->assertJsonPath('meta.overLimitCount', 2)
            ->assertJsonPath('meta.hidden', 2);

        $flags = $this->flags($response->json());
        $this->assertFalse($flags[(string) $tables[4]->id]);
        $this->assertTrue($flags[(string) $tables[5]->id]);

        $this->renew($user);

        $this->actingAs($user)->getJson('/api/dashboard/tables')
            ->assertJsonPath('meta.overLimitCount', 0)
            ->assertJsonPath('meta.hidden', 0);
    }

    // ───────── الإعلانات (حد 3) ─────────

    public function test_ads_over_the_free_limit_are_flagged_independently_of_is_hidden(): void
    {
        $user = $this->owner('cafe', null, 'paid', now()->subDay());
        $place = $this->placeOf($user);

        $ads = [];
        for ($i = 1; $i <= 5; $i++) {
            $a = new DashboardAd(['type' => 'offer', 'title' => "إعلان $i", 'is_hidden' => $i === 1]);
            $a->place_id = $place->id;
            $a->save();
            $ads[] = $a;
        }

        $response = $this->actingAs($user)->getJson('/api/dashboard/ads')->assertOk();
        $response->assertJsonPath('meta.limit', 3)->assertJsonPath('meta.overLimitCount', 2);

        $byId = collect($response->json('data'))->keyBy('id');

        // أقدم 3 تبقى (حتى لو الإعلان الأول مخفي بإرادة المالك)، والرابع والخامس فوق الحد.
        $this->assertFalse($byId[(string) $ads[0]->id]['overLimit']);
        $this->assertTrue($byId[(string) $ads[0]->id]['isHidden']);
        $this->assertFalse($byId[(string) $ads[2]->id]['overLimit']);
        $this->assertTrue($byId[(string) $ads[3]->id]['overLimit']);
        $this->assertFalse($byId[(string) $ads[3]->id]['isHidden']);

        $this->actingAs($user)->getJson('/api/dashboard/ads/'.$ads[4]->id)
            ->assertOk()->assertJsonPath('data.overLimit', true);

        $this->renew($user);

        $this->actingAs($user)->getJson('/api/dashboard/ads')->assertJsonPath('meta.overLimitCount', 0);
    }

    // ───────── منتجات المتاجر: حدّان (الإجمالي 10 والمخفّضة 5) ─────────

    /**
     * @param  array<int, int>  $discounted  أرقام المنتجات (1-based) التي عليها خصم
     * @return array<int, DashboardStoreProduct> مرتبة من الأقدم للأحدث (الفهرس 0 = المنتج 1)
     */
    private function products(User $owner, int $count, array $discounted = []): array
    {
        $products = [];

        for ($i = 1; $i <= $count; $i++) {
            $p = new DashboardStoreProduct([
                'name' => "منتج $i",
                'price' => 10,
                'has_discount' => in_array($i, $discounted, true),
            ]);
            $p->place_id = $this->placeOf($owner)->id;
            $p->save();
            $products[] = $p;
        }

        return $products;
    }

    public function test_store_products_apply_total_limit_then_discount_limit_on_visible_ones(): void
    {
        // menswear: حد إجمالي 10 (الافتراضي) وحد مخفّضة 5.
        $user = $this->owner('store', 'menswear', 'paid', now()->subDay());

        // خصم على 1..7 ضمن أقدم 10، وعلى 11 (مخفي أصلاً بحد الإجمالي فلا ياكل من حصة الخصم).
        $products = $this->products($user, 12, [1, 2, 3, 4, 5, 6, 7, 11]);

        $response = $this->actingAs($user)->getJson('/api/dashboard/store-products')->assertOk();
        $response->assertJsonPath('meta.limit', 10)
            ->assertJsonPath('meta.discountLimit', 5)
            ->assertJsonPath('meta.overLimitCount', 4);

        $flags = $this->flags($response->json());

        // 1..5: ضمن الإجمالي وضمن حصة الخصم → ظاهرة.
        foreach ([0, 1, 2, 3, 4] as $i) {
            $this->assertFalse($flags[(string) $products[$i]->id], 'المنتج '.($i + 1).' يجب أن يبقى ظاهراً');
        }

        // 6 و7: ضمن الإجمالي لكن تجاوزا حصة الخصم → فوق الحد.
        $this->assertTrue($flags[(string) $products[5]->id]);
        $this->assertTrue($flags[(string) $products[6]->id]);

        // 8..10: بلا خصم وضمن الإجمالي → ظاهرة.
        foreach ([7, 8, 9] as $i) {
            $this->assertFalse($flags[(string) $products[$i]->id], 'المنتج '.($i + 1).' يجب أن يبقى ظاهراً');
        }

        // 11 و12: فوق حد الإجمالي.
        $this->assertTrue($flags[(string) $products[10]->id]);
        $this->assertTrue($flags[(string) $products[11]->id]);

        $this->assertSame(12, DashboardStoreProduct::count());
    }

    public function test_store_product_flag_follows_on_show_and_update_and_deletes_shift_the_window(): void
    {
        $user = $this->owner('store', 'menswear', 'paid', now()->subDay());
        $products = $this->products($user, 11);

        $this->actingAs($user)->getJson('/api/dashboard/store-products/'.$products[10]->id)
            ->assertOk()->assertJsonPath('data.overLimit', true);

        $this->actingAs($user)->patchJson('/api/dashboard/store-products/'.$products[10]->id, ['name' => 'معدّل'])
            ->assertOk()->assertJsonPath('data.name', 'معدّل')->assertJsonPath('data.overLimit', true);

        // حذف أقدم منتج: المنتج 11 يدخل ضمن الباقة.
        $this->actingAs($user)->deleteJson('/api/dashboard/store-products/'.$products[0]->id)->assertNoContent();

        $this->actingAs($user)->getJson('/api/dashboard/store-products/'.$products[10]->id)
            ->assertOk()->assertJsonPath('data.overLimit', false);
        $this->actingAs($user)->getJson('/api/dashboard/store-products')
            ->assertJsonPath('meta.overLimitCount', 0);
    }

    public function test_store_products_active_paid_and_renewal_have_nothing_over_the_limit(): void
    {
        $active = $this->owner('store', 'menswear', 'paid', now()->addMonth());
        $this->products($active, 12, [1, 2, 3, 4, 5, 6, 7]);
        $this->actingAs($active)->getJson('/api/dashboard/store-products')
            ->assertJsonPath('meta.overLimitCount', 0);

        $expired = $this->owner('store', 'menswear', 'paid', now()->subDay());
        $this->products($expired, 12, [1, 2, 3, 4, 5, 6, 7]);
        $this->actingAs($expired)->getJson('/api/dashboard/store-products')
            ->assertJsonPath('meta.overLimitCount', 4);

        $this->renew($expired);

        $response = $this->actingAs($expired)->getJson('/api/dashboard/store-products')->assertOk()
            ->assertJsonPath('meta.overLimitCount', 0);
        $this->assertNotContains(true, collect($response->json('data'))->pluck('overLimit')->all());
    }

    public function test_store_variant_without_discount_support_only_uses_the_total_limit(): void
    {
        // grocery: حد إجمالي 15 ولا يدعم الخصم.
        $user = $this->owner('store', 'grocery', 'paid', now()->subDay());
        $this->products($user, 17);

        $this->actingAs($user)->getJson('/api/dashboard/store-products')->assertOk()
            ->assertJsonPath('meta.limit', 15)
            ->assertJsonPath('meta.overLimitCount', 2);
    }
}