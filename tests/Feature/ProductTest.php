<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Product;
use App\Models\Regions;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(string $name = 'Test User'): User
    {
        $user = new User();
        $user->user_phone = '059'.random_int(1000000, 9999999);
        $user->user_full_name = $name;
        $user->save();

        return $user;
    }

    private function lookups(): array
    {
        $region = new Regions();
        $region->region_slug = 'r-'.Str::random(6);
        $region->region_name_en = 'Gaza';
        $region->region_name_ar = 'غزة';
        $region->save();

        $area = new Area();
        $area->area_slug = 'a-'.Str::random(6);
        $area->area_name_en = 'Rimal';
        $area->area_name_ar = 'الرمال';
        $area->region_id = $region->id;
        $area->save();

        $group = new CategoryGroup();
        $group->category_group_slug = 'g-'.Str::random(6);
        $group->category_group_name_en = 'Food';
        $group->category_group_name_ar = 'غذاء';
        $group->save();

        $category = new Category();
        $category->category_slug = 'c-'.Str::random(6);
        $category->category_name_en = 'Grains';
        $category->category_name_ar = 'حبوب';
        $category->category_group_id = $group->id;
        $category->save();

        $unit = new Unit();
        $unit->unit_slug = 'u-'.Str::random(6);
        $unit->unit_name_en = 'Kilo';
        $unit->unit_name_ar = 'كيلو';
        $unit->save();

        return ['area' => $area, 'category' => $category, 'unit' => $unit];
    }

    private function product(string $status = 'pending', array $overrides = [], ?array $lookups = null, ?User $user = null): Product
    {
        $lookups ??= $this->lookups();
        $user ??= $this->makeUser();

        $product = new Product();
        $product->forceFill(array_merge([
            'product_name_ar' => 'منتج',
            'category_id' => $lookups['category']->id,
            'product_quantity' => 1,
            'unit_id' => $lookups['unit']->id,
            'product_price' => 10,
            'product_store_name' => 'متجر',
            'area_id' => $lookups['area']->id,
            'user_id' => $user->id,
            'product_status' => $status,
        ], $overrides));
        $product->save();

        return $product->refresh();
    }

    public function test_new_product_defaults_to_pending_and_status_is_not_mass_assignable(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();

        $product = new Product();
        $product->fill([
            'product_name_ar' => 'طحين',
            'category_id' => $lookups['category']->id,
            'product_quantity' => 1,
            'unit_id' => $lookups['unit']->id,
            'product_price' => 5,
            'product_store_name' => 'متجر',
            'area_id' => $lookups['area']->id,
            'user_id' => $user->id + 100,
            'product_status' => 'active',
        ]);
        $product->user_id = $user->id;
        $product->save();

        $this->assertSame('pending', $product->refresh()->product_status);
        $this->assertSame($user->id, $product->user_id);
    }

    public function test_index_lists_products_and_filters_by_status(): void
    {
        $this->product('active', ['product_name_ar' => 'منتج-فعال']);
        $this->product('pending', ['product_name_ar' => 'منتج-معلق']);

        $this->get(route('product.index'))
            ->assertOk()
            ->assertSee('منتج-فعال')
            ->assertSee('منتج-معلق');

        $this->get(route('product.index', ['status' => 'active']))
            ->assertOk()
            ->assertSee('منتج-فعال')
            ->assertDontSee('منتج-معلق');
    }

    public function test_index_ignores_an_invalid_filter_value_with_a_warning(): void
    {
        $this->product('pending', ['product_name_ar' => 'منتج-معلق']);

        $this->get(route('product.index', ['status' => 'bad']))
            ->assertOk()
            ->assertSee('Invalid filter value ignored')
            ->assertSee('منتج-معلق');
    }

    public function test_index_json_client_gets_422_for_an_invalid_filter(): void
    {
        $this->getJson(route('product.index', ['status' => 'bad']))->assertStatus(422);
    }

    public function test_show_displays_the_user_and_store_phone(): void
    {
        $user = $this->makeUser('مستخدم تجريبي');
        $product = $this->product('pending', ['product_store_phone' => '0599123456'], null, $user);

        $this->get(route('product.show', $product->id))
            ->assertOk()
            ->assertSee('مستخدم تجريبي')
            ->assertSee($user->user_phone)
            ->assertSee('0599123456');
    }

    public function test_show_returns_404_for_an_unknown_product(): void
    {
        $this->get(route('product.show', 999999))->assertNotFound();
    }

    public function test_approve_moves_pending_to_active_and_cannot_repeat(): void
    {
        $product = $this->product('pending');

        $this->postJson(route('product_approve', $product->id))
            ->assertOk()
            ->assertJson(['redirect' => route('product.show', $product->id)]);

        $this->assertSame('active', $product->refresh()->product_status);

        $this->postJson(route('product_approve', $product->id))
            ->assertStatus(409)
            ->assertJson(['icon' => 'error']);
    }

    public function test_approve_is_refused_when_hidden(): void
    {
        $product = $this->product('hidden');

        $this->postJson(route('product_approve', $product->id))->assertStatus(409);

        $this->assertSame('hidden', $product->refresh()->product_status);
    }

    public function test_hide_and_unhide_only_work_on_the_right_status(): void
    {
        $active = $this->product('active');
        $hidden = $this->product('hidden');
        $pending = $this->product('pending');

        $this->postJson(route('product_hide', $active->id))->assertOk();
        $this->assertSame('hidden', $active->refresh()->product_status);

        $this->postJson(route('product_unhide', $hidden->id))->assertOk();
        $this->assertSame('active', $hidden->refresh()->product_status);

        $this->postJson(route('product_hide', $pending->id))->assertStatus(409);
        $this->postJson(route('product_unhide', $pending->id))->assertStatus(409);
        $this->assertSame('pending', $pending->refresh()->product_status);
    }

    public function test_destroy_removes_only_that_product_and_its_receipt(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/wrong.jpg', 'x');
        Storage::disk('local')->put('receipts/keep.jpg', 'x');

        $lookups = $this->lookups();
        $user = $this->makeUser();

        $wrong = $this->product('pending', ['product_receipt_image' => 'receipts/wrong.jpg'], $lookups, $user);
        $keep = $this->product('active', ['product_receipt_image' => 'receipts/keep.jpg'], $lookups, $user);
        $other = $this->product('pending', [], $lookups, $user);

        $this->deleteJson(route('product.destroy', $wrong->id))
            ->assertOk()
            ->assertJson(['icon' => 'success']);

        $this->assertNull(Product::find($wrong->id));
        $this->assertNotNull(Product::find($keep->id));
        $this->assertNotNull(Product::find($other->id));
        Storage::disk('local')->assertMissing('receipts/wrong.jpg');
        Storage::disk('local')->assertExists('receipts/keep.jpg');
    }

    public function test_destroy_works_for_every_status_and_removes_the_receipt(): void
    {
        Storage::fake('local');

        foreach (Product::STATUSES as $status) {
            $path = "receipts/{$status}.jpg";
            Storage::disk('local')->put($path, 'x');

            $product = $this->product($status, ['product_receipt_image' => $path]);

            $this->deleteJson(route('product.destroy', $product->id))
                ->assertOk()
                ->assertJson(['icon' => 'success']);

            $this->assertNull(Product::find($product->id));
            Storage::disk('local')->assertMissing($path);
        }
    }

    public function test_destroy_returns_404_for_an_unknown_product(): void
    {
        $this->deleteJson(route('product.destroy', 999999))->assertNotFound();
    }

    public function test_receipt_is_served_from_the_private_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/a.jpg', 'image-bytes');

        $product = $this->product('pending', ['product_receipt_image' => 'receipts/a.jpg']);

        $this->get(route('product_receipt', $product->id))->assertOk();
    }

    public function test_receipt_returns_404_when_missing(): void
    {
        Storage::fake('local');

        $withoutReceipt = $this->product('pending');
        $missingFile = $this->product('pending', ['product_receipt_image' => 'receipts/none.jpg']);

        $this->get(route('product_receipt', $withoutReceipt->id))->assertNotFound();
        $this->get(route('product_receipt', $missingFile->id))->assertNotFound();
    }

    public function test_category_unit_and_area_with_products_cannot_be_deleted(): void
    {
        $lookups = $this->lookups();
        $this->product('pending', [], $lookups);

        $this->deleteJson('/cms/admins/category/'.$lookups['category']->id)->assertStatus(409);
        $this->deleteJson('/cms/admins/unit/'.$lookups['unit']->id)->assertStatus(409);
        $this->deleteJson('/cms/admins/area/'.$lookups['area']->id)->assertStatus(409);

        $this->assertNotNull(Category::find($lookups['category']->id));
        $this->assertNotNull(Unit::find($lookups['unit']->id));
        $this->assertNotNull(Area::find($lookups['area']->id));
    }

    public function test_product_stays_readable_after_its_user_is_soft_deleted(): void
    {
        $user = $this->makeUser('مستخدم محذوف');
        $product = $this->product('pending', [], null, $user);

        $user->delete();

        $this->assertSame('مستخدم محذوف', $product->refresh()->user->user_full_name);
    }
}