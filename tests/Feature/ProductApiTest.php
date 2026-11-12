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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProductApiTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = new User();
        $user->user_phone = '059'.random_int(1000000, 9999999);
        $user->user_full_name = 'Test User';
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

    private function validData(array $lookups, array $overrides = []): array
    {
        return array_merge([
            'product_name_ar' => 'طحين',
            'category_id' => $lookups['category']->id,
            'product_quantity' => 1,
            'unit_id' => $lookups['unit']->id,
            'product_price' => 5.5,
            'product_store_name' => 'متجر الأمل',
            'area_id' => $lookups['area']->id,
        ], $overrides);
    }

    private function makeProduct(array $lookups, User $user, string $status, array $overrides = []): Product
    {
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

    public function test_guest_cannot_store_a_product(): void
    {
        $data = $this->validData($this->lookups());

        $this->postJson('/api/products', $data)->assertStatus(401);

        $this->assertSame(0, Product::count());
    }

    public function test_store_creates_pending_product_for_the_logged_in_user(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();
        $data = $this->validData($this->lookups(), [
            'user_id' => $other->id,
            'product_status' => 'active',
        ]);

        $this->actingAs($user)->postJson('/api/products', $data)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $product = Product::firstOrFail();

        $this->assertSame($user->id, $product->user_id);
        $this->assertSame('pending', $product->product_status);
    }

    public function test_store_requires_at_least_one_name(): void
    {
        $data = $this->validData($this->lookups(), ['product_name_ar' => null]);

        $this->actingAs($this->makeUser())->postJson('/api/products', $data)
            ->assertStatus(400)
            ->assertJson(['icon' => 'error']);

        $this->assertSame(0, Product::count());
    }

    public function test_store_accepts_english_name_only(): void
    {
        $data = $this->validData($this->lookups(), ['product_name_ar' => null, 'product_name_en' => 'Flour']);

        $this->actingAs($this->makeUser())->postJson('/api/products', $data)->assertStatus(201);

        $this->assertSame('Flour', Product::firstOrFail()->product_name_en);
    }

    public function test_store_rejects_invalid_prices(): void
    {
        $user = $this->makeUser();
        $lookups = $this->lookups();

        foreach ([0, -5, 0.99, 'abc', 100000000] as $price) {
            $this->actingAs($user)
                ->postJson('/api/products', $this->validData($lookups, ['product_price' => $price]))
                ->assertStatus(400);
        }

        $this->assertSame(0, Product::count());
    }

    public function test_store_rejects_zero_or_negative_quantity(): void
    {
        $user = $this->makeUser();
        $lookups = $this->lookups();

        foreach ([0, -1] as $quantity) {
            $this->actingAs($user)
                ->postJson('/api/products', $this->validData($lookups, ['product_quantity' => $quantity]))
                ->assertStatus(400);
        }

        $this->assertSame(0, Product::count());
    }

    public function test_store_rejects_unknown_or_inactive_relations(): void
    {
        $user = $this->makeUser();
        $lookups = $this->lookups();

        $this->actingAs($user)
            ->postJson('/api/products', $this->validData($lookups, ['category_id' => 999999]))
            ->assertStatus(400);

        $lookups['category']->category_is_active = false;
        $lookups['category']->save();

        $this->actingAs($user)
            ->postJson('/api/products', $this->validData($lookups))
            ->assertStatus(400);

        $lookups = $this->lookups();
        $lookups['unit']->unit_is_active = false;
        $lookups['unit']->save();

        $this->actingAs($user)
            ->postJson('/api/products', $this->validData($lookups))
            ->assertStatus(400);

        $this->assertSame(0, Product::count());
    }

    public function test_store_saves_the_receipt_on_the_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $data = $this->validData($this->lookups(), [
            'product_receipt_image' => UploadedFile::fake()->image('receipt.jpg', 400, 400),
        ]);

        $this->actingAs($this->makeUser())
            ->post('/api/products', $data, ['Accept' => 'application/json'])
            ->assertStatus(201);

        $path = Product::firstOrFail()->product_receipt_image;

        $this->assertStringStartsWith('receipts/', $path);
        Storage::disk('local')->assertExists($path);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_store_rejects_files_that_are_not_real_images(): void
    {
        Storage::fake('local');
        $user = $this->makeUser();
        $lookups = $this->lookups();

        // ملف حقيقي: UploadedFile::fake() بيحدد الـ mime من اسم الملف، فما بيصلح لفحص المحتوى
        $textPath = tempnam(sys_get_temp_dir(), 'rcp');
        file_put_contents($textPath, 'this is not an image');

        $files = [
            new UploadedFile($textPath, 'receipt.jpg', null, null, true),
            UploadedFile::fake()->create('receipt.pdf', 10, 'application/pdf'),
        ];

        foreach ($files as $file) {
            $this->actingAs($user)
                ->post('/api/products', $this->validData($lookups, ['product_receipt_image' => $file]), ['Accept' => 'application/json'])
                ->assertStatus(400);
        }

        @unlink($textPath);

        $this->assertSame(0, Product::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }
    
    public function test_search_returns_only_active_products_matching_either_name(): void
    {
        $user = $this->makeUser();
        $lookups = $this->lookups();

        $this->makeProduct($lookups, $user, 'active', ['product_name_ar' => 'زيت زيتون']);
        $this->makeProduct($lookups, $user, 'active', ['product_name_ar' => null, 'product_name_en' => 'Olive oil']);
        $this->makeProduct($lookups, $user, 'pending', ['product_name_ar' => 'زيت ذرة']);
        $this->makeProduct($lookups, $user, 'hidden', ['product_name_ar' => 'زيت سمسم']);
        $this->makeProduct($lookups, $user, 'active', ['product_name_ar' => 'سكر']);

        $this->getJson('/api/products/search?'.http_build_query(['q' => 'زيت']))
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.category.name_ar', 'حبوب')
            ->assertJsonPath('data.0.unit.name_ar', 'كيلو')
            ->assertJsonPath('data.0.area.name_ar', 'الرمال');

        $this->getJson('/api/products/search?'.http_build_query(['q' => 'olive']))
            ->assertOk()
            ->assertJsonCount(1, 'data');
    }

    public function test_search_requires_a_valid_query(): void
    {
        $this->getJson('/api/products/search')->assertStatus(400);
        $this->getJson('/api/products/search?q=a')->assertStatus(400);
        $this->getJson('/api/products/search?'.http_build_query(['q' => str_repeat('a', 51)]))->assertStatus(400);
    }

    public function test_search_treats_wildcards_as_plain_characters(): void
    {
        $user = $this->makeUser();
        $lookups = $this->lookups();

        $this->makeProduct($lookups, $user, 'active', ['product_name_ar' => 'سكر']);
        $this->makeProduct($lookups, $user, 'active', ['product_name_ar' => 'ملح']);

        $this->getJson('/api/products/search?'.http_build_query(['q' => '%%']))
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/products/search?'.http_build_query(['q' => '__']))
            ->assertOk()
            ->assertJsonCount(0, 'data');
    }

    public function test_search_does_not_expose_private_fields(): void
    {
        $user = $this->makeUser();

        $this->makeProduct($this->lookups(), $user, 'active', [
            'product_name_ar' => 'عدس',
            'product_store_phone' => '0599000000',
            'product_receipt_image' => 'receipts/secret.jpg',
        ]);

        $response = $this->getJson('/api/products/search?'.http_build_query(['q' => 'عدس']))
            ->assertOk()
            ->assertJsonCount(1, 'data');

        foreach (['user_id', 'product_status', 'product_store_phone', 'product_receipt_image'] as $field) {
            $response->assertJsonMissingPath('data.0.'.$field);
        }
    }
}