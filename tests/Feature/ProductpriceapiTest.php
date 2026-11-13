<?php

namespace Tests\Feature;

use App\Models\PriceVote;
use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class ProductPriceApiTest extends TestCase
{
    use BuildsProductPrices;
    use RefreshDatabase;

    private function validData(array $product, array $lookups, array $overrides = []): array
    {
        return array_merge([
            'product_id' => $product['id'],
            'region' => $lookups['area']->area_slug,
            'price' => 7.5,
            'store_name' => 'بقالة أبو محمد',
        ], $overrides);
    }

    // ---------- المخطط ----------

    public function test_schema_has_the_agreed_columns_and_vote_unique_key(): void
    {
        foreach ([
            'product_price_value', 'product_price_store_name', 'product_price_store_address',
            'product_price_store_phone', 'product_price_receipt_image', 'product_price_status',
            'rejection_reason', 'reviewed_at', 'confirm_count', 'report_count',
            'product_id', 'area_id', 'user_id', 'created_at', 'updated_at',
        ] as $column) {
            $this->assertTrue(Schema::hasColumn('product_prices', $column), "missing product_prices.$column");
        }

        $this->assertTrue(Schema::hasColumns('price_votes', ['product_price_id', 'user_id', 'price_vote_type', 'created_at', 'updated_at']));
    }

    public function test_database_rejects_a_second_vote_from_the_same_user(): void
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser();
        $voter = $this->makeUser();
        $price = $this->makePrice($this->makeProduct($lookups, $owner), $lookups, $owner);

        $this->makeVote($price, $voter, 'confirm');

        $this->expectException(QueryException::class);
        $this->makeVote($price, $voter, 'report');
    }

    // ---------- الـ feed ----------

    public function test_feed_is_public_and_returns_only_approved_prices_in_the_frontend_shape(): void
    {
        $lookups = $this->lookups('Nuseirat');
        $owner = $this->makeUser();
        $product = $this->makeProduct($lookups, $owner);

        $approved = $this->makePrice($product, $lookups, $owner, 'approved', [
            'product_price_value' => 8,
            'confirm_count' => 5,
            'product_price_store_phone' => '0591234567',
        ]);
        $this->makePrice($product, $lookups, $owner, 'pending');
        $this->makePrice($product, $lookups, $owner, 'rejected');
        $this->makePrice($product, $lookups, $owner, 'flagged');

        $response = $this->getJson('/api/prices?limit=5&page=1')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('total', 1)
            ->assertJsonPath('data.0.price_id', $approved->id)
            ->assertJsonPath('data.0.product_id', $product->id)
            ->assertJsonPath('data.0.product_name_ar', 'سكر')
            ->assertJsonPath('data.0.product_name_en', 'Sugar')
            ->assertJsonPath('data.0.quantity', '1.00')
            ->assertJsonPath('data.0.unit', $lookups['unit']->unit_slug)
            ->assertJsonPath('data.0.store_name', 'بقالة أبو محمد')
            ->assertJsonPath('data.0.region', 'Nuseirat')
            ->assertJsonPath('data.0.category', $lookups['category']->category_slug)
            ->assertJsonPath('data.0.price', '8.00')
            ->assertJsonPath('data.0.confirm_count', 5)
            ->assertJsonPath('data.0.report_count', 0)
            ->assertJsonPath('data.0.my_vote', null);

        $item = $response->json('data.0');
        $this->assertArrayHasKey('created_at', $item);
        // ما في بيانات حساسة بالبطاقة
        foreach (['user_id', 'product_price_store_phone', 'store_phone', 'product_price_receipt_image', 'receipt'] as $key) {
            $this->assertArrayNotHasKey($key, $item);
        }
        $this->assertStringNotContainsString('0591234567', $response->getContent());
    }

    public function test_feed_hides_prices_of_products_that_are_not_active(): void
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser();
        $hidden = $this->makeProduct($lookups, $owner, 'hidden');
        $this->makePrice($hidden, $lookups, $owner);

        $this->getJson('/api/prices')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('total', 0);
    }

    public function test_feed_is_newest_first_and_paginates_with_total(): void
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser();
        $product = $this->makeProduct($lookups, $owner);

        $first = $this->makePrice($product, $lookups, $owner, 'approved', ['created_at' => now()->subMinutes(3)]);
        $second = $this->makePrice($product, $lookups, $owner, 'approved', ['created_at' => now()->subMinutes(2)]);
        $third = $this->makePrice($product, $lookups, $owner, 'approved', ['created_at' => now()->subMinute()]);

        $this->getJson('/api/prices?limit=2&page=1')
            ->assertOk()
            ->assertJsonPath('total', 3)
            ->assertJsonPath('has_more', true)
            ->assertJsonPath('data.0.price_id', $third->id)
            ->assertJsonPath('data.1.price_id', $second->id);

        $this->getJson('/api/prices?limit=2&page=2')
            ->assertOk()
            ->assertJsonPath('has_more', false)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price_id', $first->id);
    }

    public function test_feed_filters_by_region_and_category(): void
    {
        $nuseirat = $this->lookups('Nuseirat');
        $rafah = $this->lookups('Rafah');
        $owner = $this->makeUser();

        $inNuseirat = $this->makePrice($this->makeProduct($nuseirat, $owner), $nuseirat, $owner);
        $inRafah = $this->makePrice($this->makeProduct($rafah, $owner), $rafah, $owner);

        $this->getJson('/api/prices?region=Nuseirat')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price_id', $inNuseirat->id);

        $this->getJson('/api/prices?category='.$rafah['category']->category_slug)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.price_id', $inRafah->id);

        $this->getJson('/api/prices?region=Nuseirat&category='.$rafah['category']->category_slug)
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->getJson('/api/prices?region=Unknown')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_feed_fills_my_vote_for_the_logged_in_user_only(): void
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser();
        $me = $this->makeUser();
        $other = $this->makeUser();
        $price = $this->makePrice($this->makeProduct($lookups, $owner), $lookups, $owner);

        $this->makeVote($price, $me, 'confirm');
        $this->makeVote($price, $other, 'report');

        $this->actingAs($me)->getJson('/api/prices')->assertOk()->assertJsonPath('data.0.my_vote', 'confirm');
        $this->actingAs($other)->getJson('/api/prices')->assertOk()->assertJsonPath('data.0.my_vote', 'report');
    }

    public function test_feed_rejects_invalid_query_values(): void
    {
        $this->getJson('/api/prices?limit=0')->assertStatus(400)->assertJson(['icon' => 'error']);
        $this->getJson('/api/prices?limit=500')->assertStatus(400);
        $this->getJson('/api/prices?page=abc')->assertStatus(400);
    }

    // ---------- تسجيل سعر ----------

    public function test_guest_cannot_store_a_price(): void
    {
        $lookups = $this->lookups();
        $product = $this->makeProduct($lookups, $this->makeUser());

        $this->postJson('/api/prices', $this->validData($product->only('id'), $lookups))->assertStatus(401);

        $this->assertSame(0, ProductPrice::count());
    }

    public function test_store_creates_a_pending_price_owned_by_the_logged_in_user(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $other = $this->makeUser();
        $product = $this->makeProduct($lookups, $other);

        $data = $this->validData($product->only('id'), $lookups, [
            'store_address' => 'قرب مجمع الرازي',
            'store_phone' => '0591234567',
            // محاولات تجاوز: لازم تنتجاهل
            'user_id' => $other->id,
            'product_price_status' => 'approved',
            'confirm_count' => 99,
            'report_count' => 99,
        ]);

        $this->actingAs($user)->postJson('/api/prices', $data)
            ->assertStatus(201)
            ->assertJsonPath('data.status', 'pending');

        $price = ProductPrice::firstOrFail();

        $this->assertSame($user->id, (int) $price->user_id);
        $this->assertSame('pending', $price->product_price_status);
        $this->assertSame($product->id, (int) $price->product_id);
        $this->assertSame($lookups['area']->id, (int) $price->area_id);
        $this->assertSame('7.50', $price->product_price_value);
        $this->assertSame('قرب مجمع الرازي', $price->product_price_store_address);
        $this->assertSame(0, $price->confirm_count);
        $this->assertSame(0, $price->report_count);
        // ما بنلمس الكتالوج
        $this->assertSame(1, Product::count());
    }

    public function test_store_allows_missing_optional_fields(): void
    {
        $lookups = $this->lookups();
        $product = $this->makeProduct($lookups, $this->makeUser());

        $this->actingAs($this->makeUser())->postJson('/api/prices', $this->validData($product->only('id'), $lookups))
            ->assertStatus(201);

        $price = ProductPrice::firstOrFail();
        $this->assertNull($price->product_price_store_address);
        $this->assertNull($price->product_price_store_phone);
        $this->assertNull($price->product_price_receipt_image);
    }

    public function test_store_requires_an_existing_active_product(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $hidden = $this->makeProduct($lookups, $user, 'hidden');
        $pending = $this->makeProduct($lookups, $user, 'pending');

        foreach ([$hidden->id, $pending->id, 999999] as $productId) {
            $this->actingAs($user)->postJson('/api/prices', $this->validData(['id' => $productId], $lookups))
                ->assertStatus(400)
                ->assertJson(['icon' => 'error']);
        }

        $this->assertSame(0, ProductPrice::count());
    }

    public function test_store_rejects_unknown_region_and_bad_values(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user)->only('id');

        $bad = [
            ['region' => 'Atlantis'],
            ['price' => 0],
            ['price' => -3],
            ['price' => 'abc'],
            ['price' => 1.234],
            ['price' => 100000000],
            ['store_name' => ''],
            ['store_name' => str_repeat('م', 121)],
            ['store_address' => str_repeat('a', 256)],
            ['store_phone' => 'abc'],
        ];

        foreach ($bad as $override) {
            $this->actingAs($user)->postJson('/api/prices', $this->validData($product, $lookups, $override))
                ->assertStatus(400)
                ->assertJson(['icon' => 'error']);
        }

        $this->assertSame(0, ProductPrice::count());
    }

    public function test_store_keeps_two_decimals(): void
    {
        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user)->only('id');

        $this->actingAs($user)->postJson('/api/prices', $this->validData($product, $lookups, ['price' => 3.5]))->assertStatus(201);
        $this->actingAs($user)->postJson('/api/prices', $this->validData($product, $lookups, ['price' => '12.99']))->assertStatus(201);

        $this->assertSame(['3.50', '12.99'], ProductPrice::orderBy('id')->get()->pluck('product_price_value')->all());
    }

    public function test_store_saves_the_receipt_on_the_private_disk(): void
    {
        Storage::fake('local');
        Storage::fake('public');

        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user)->only('id');

        $this->actingAs($user)->post('/api/prices', $this->validData($product, $lookups, [
            'receipt_image' => UploadedFile::fake()->image('receipt.jpg'),
        ]), ['Accept' => 'application/json'])->assertStatus(201);

        $path = ProductPrice::firstOrFail()->product_price_receipt_image;

        $this->assertNotNull($path);
        $this->assertStringStartsWith('receipts/', $path);
        // المسار أطول من 45 حرف: كان رح يفشل بعمود VARCHAR(45)
        $this->assertGreaterThan(45, strlen($path));
        Storage::disk('local')->assertExists($path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_store_rejects_non_image_receipts(): void
    {
        Storage::fake('local');

        $lookups = $this->lookups();
        $user = $this->makeUser();
        $product = $this->makeProduct($lookups, $user)->only('id');

        $this->actingAs($user)->post('/api/prices', $this->validData($product, $lookups, [
            'receipt_image' => UploadedFile::fake()->create('receipt.pdf', 100, 'application/pdf'),
        ]), ['Accept' => 'application/json'])->assertStatus(400);

        $this->assertSame(0, ProductPrice::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    // ---------- التصويت ----------

    private function votable(): array
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser('Owner');
        $price = $this->makePrice($this->makeProduct($lookups, $owner), $lookups, $owner);

        return [$price, $owner, $lookups];
    }

    public function test_guest_cannot_vote(): void
    {
        [$price] = $this->votable();

        $this->postJson("/api/prices/{$price->id}/vote", ['vote' => 'confirm'])->assertStatus(401);

        $this->assertSame(0, PriceVote::count());
    }

    public function test_confirm_adds_a_vote_and_updates_the_counters(): void
    {
        [$price] = $this->votable();
        $user = $this->makeUser();

        $this->actingAs($user)->postJson("/api/prices/{$price->id}/vote", ['vote' => 'confirm'])
            ->assertOk()
            ->assertJsonPath('data.price_id', $price->id)
            ->assertJsonPath('data.my_vote', 'confirm')
            ->assertJsonPath('data.confirm_count', 1)
            ->assertJsonPath('data.report_count', 0)
            ->assertJsonPath('data.removed', false);

        $price->refresh();
        $this->assertSame(1, $price->confirm_count);
        $this->assertSame(1, PriceVote::where('user_id', $user->id)->count());
    }

    public function test_repeating_the_same_vote_does_not_double_count(): void
    {
        [$price] = $this->votable();
        $user = $this->makeUser();

        foreach (range(1, 3) as $i) {
            $this->actingAs($user)->postJson("/api/prices/{$price->id}/vote", ['vote' => 'confirm'])
                ->assertOk()
                ->assertJsonPath('data.confirm_count', 1);
        }

        $this->assertSame(1, PriceVote::count());
    }

    public function test_switching_the_vote_moves_the_counts(): void
    {
        [$price] = $this->votable();
        $user = $this->makeUser();

        $this->actingAs($user)->postJson("/api/prices/{$price->id}/vote", ['vote' => 'confirm'])->assertOk();

        $this->actingAs($user)->postJson("/api/prices/{$price->id}/vote", ['vote' => 'report'])
            ->assertOk()
            ->assertJsonPath('data.my_vote', 'report')
            ->assertJsonPath('data.confirm_count', 0)
            ->assertJsonPath('data.report_count', 1);

        $this->assertSame(1, PriceVote::count());
        $this->assertSame('report', PriceVote::firstOrFail()->price_vote_type);
    }

    public function test_null_vote_removes_the_users_vote(): void
    {
        [$price] = $this->votable();
        $user = $this->makeUser();
        $this->makeVote($price, $user, 'confirm');
        $price->forceFill(['confirm_count' => 1])->save();

        $this->actingAs($user)->postJson("/api/prices/{$price->id}/vote", ['vote' => null])
            ->assertOk()
            ->assertJsonPath('data.my_vote', null)
            ->assertJsonPath('data.confirm_count', 0);

        $this->assertSame(0, PriceVote::count());
        // إلغاء صوت مش موجود ما بيكسر شي
        $this->actingAs($user)->postJson("/api/prices/{$price->id}/vote", ['vote' => null])->assertOk();
    }

    public function test_counters_are_recomputed_from_the_votes_table(): void
    {
        [$price] = $this->votable();
        // عدّاد منحرف يدوياً
        $price->forceFill(['confirm_count' => 50, 'report_count' => 40])->save();

        $this->actingAs($this->makeUser())->postJson("/api/prices/{$price->id}/vote", ['vote' => 'confirm'])
            ->assertOk()
            ->assertJsonPath('data.confirm_count', 1)
            ->assertJsonPath('data.report_count', 0);
    }

    public function test_owner_cannot_vote_on_their_own_price(): void
    {
        [$price, $owner] = $this->votable();

        $this->actingAs($owner)->postJson("/api/prices/{$price->id}/vote", ['vote' => 'confirm'])
            ->assertStatus(403)
            ->assertJson(['icon' => 'error']);

        $this->assertSame(0, PriceVote::count());
    }

    public function test_cannot_vote_on_prices_that_are_not_approved_or_do_not_exist(): void
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser();
        $product = $this->makeProduct($lookups, $owner);
        $user = $this->makeUser();

        foreach (['pending', 'rejected', 'flagged'] as $status) {
            $price = $this->makePrice($product, $lookups, $owner, $status);

            $this->actingAs($user)->postJson("/api/prices/{$price->id}/vote", ['vote' => 'confirm'])->assertStatus(404);
        }

        $this->actingAs($user)->postJson('/api/prices/999999/vote', ['vote' => 'confirm'])->assertStatus(404);
        $this->assertSame(0, PriceVote::count());
    }

    public function test_vote_value_is_validated(): void
    {
        [$price] = $this->votable();
        $user = $this->makeUser();

        $this->actingAs($user)->postJson("/api/prices/{$price->id}/vote", ['vote' => 'like'])->assertStatus(400);
        $this->actingAs($user)->postJson("/api/prices/{$price->id}/vote", [])->assertStatus(400);
        $this->actingAs($user)->postJson("/api/prices/{$price->id}/vote", ['vote' => ['confirm']])->assertStatus(400);

        $this->assertSame(0, PriceVote::count());
    }

    public function test_price_is_flagged_and_removed_from_the_feed_after_more_than_three_reports(): void
    {
        [$price] = $this->votable();

        // 3 بلاغات: لسه ظاهر
        foreach (range(1, ProductPrice::MAX_REPORTS) as $i) {
            $this->actingAs($this->makeUser())->postJson("/api/prices/{$price->id}/vote", ['vote' => 'report'])
                ->assertOk()
                ->assertJsonPath('data.removed', false);
        }

        $this->assertSame('approved', $price->refresh()->product_price_status);
        $this->getJson('/api/prices')->assertJsonCount(1, 'data');

        // البلاغ الرابع: بينسحب
        $this->actingAs($this->makeUser())->postJson("/api/prices/{$price->id}/vote", ['vote' => 'report'])
            ->assertOk()
            ->assertJsonPath('data.removed', true)
            ->assertJsonPath('data.report_count', 4);

        $this->assertSame('flagged', $price->refresh()->product_price_status);
        $this->getJson('/api/prices')->assertJsonCount(0, 'data');
    }
}