<?php

namespace Tests\Feature;

use App\Models\PriceVote;
use App\Models\ProductPrice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

class ProductPriceCmsTest extends TestCase
{
    use BuildsProductPrices;
    use RefreshDatabase;

    private function priceWithStatus(string $status, array $overrides = []): ProductPrice
    {
        $lookups = $this->lookups();
        $user = $this->makeUser('Reporter');
        return $this->makePrice($this->makeProduct($lookups, $user), $lookups, $user, $status, $overrides);
    }

    // ---------- القائمة ----------
    public function test_index_lists_prices_with_pending_and_flagged_first(): void
    {
        $approved = $this->priceWithStatus('approved', ['product_price_store_name' => 'متجر-معتمد']);
        $flagged = $this->priceWithStatus('flagged', ['product_price_store_name' => 'متجر-مبلغ']);
        $pending = $this->priceWithStatus('pending', ['product_price_store_name' => 'متجر-جديد']);

        $this->get(route('product_price.index'))
            ->assertOk()
            ->assertSeeInOrder(['متجر-جديد', 'متجر-مبلغ', 'متجر-معتمد'])
            ->assertSee('Reporter');

        $this->assertSame([$pending->id, $flagged->id, $approved->id],
            $this->get(route('product_price.index'))->viewData('prices')->pluck('id')->all());
    }

    public function test_index_filters_by_status_and_ignores_invalid_filters(): void
    {
        $this->priceWithStatus('approved', ['product_price_store_name' => 'متجر-معتمد']);
        $this->priceWithStatus('pending', ['product_price_store_name' => 'متجر-جديد']);

        $this->get(route('product_price.index', ['status' => 'pending']))
            ->assertOk()
            ->assertSee('متجر-جديد')
            ->assertDontSee('متجر-معتمد');

        // قيمة غلط: بتنتجاهل وبيظهر تنبيه
        $this->get(route('product_price.index', ['status' => 'bogus']))
            ->assertOk()
            ->assertSee('Invalid filter value ignored')
            ->assertSee('متجر-معتمد');

        // عميل JSON: 422
        $this->getJson(route('product_price.index', ['status' => 'bogus']))->assertStatus(422);
    }

    public function test_index_filters_by_area_and_category(): void
    {
        $a = $this->lookups();
        $b = $this->lookups();
        $user = $this->makeUser();

        $this->makePrice($this->makeProduct($a, $user), $a, $user, 'pending', ['product_price_store_name' => 'متجر-أ']);
        $this->makePrice($this->makeProduct($b, $user), $b, $user, 'pending', ['product_price_store_name' => 'متجر-ب']);

        $this->get(route('product_price.index', ['area' => $a['area']->id]))
            ->assertSee('متجر-أ')->assertDontSee('متجر-ب');

        $this->get(route('product_price.index', ['category' => $b['category']->id]))
            ->assertSee('متجر-ب')->assertDontSee('متجر-أ');
    }

    public function test_index_shows_an_empty_state(): void
    {
        $this->get(route('product_price.index'))->assertOk()->assertSee('No prices found.');
    }

    // ---------- العرض والفاتورة ----------

    public function test_show_renders_the_price_details(): void
    {
        $price = $this->priceWithStatus('pending', [
            'product_price_store_name' => 'بقالة الشروق',
            'product_price_store_phone' => '0591234567',
        ]);

        $this->get(route('product_price.show', $price->id))
            ->assertOk()
            ->assertSee('بقالة الشروق')
            ->assertSee('0591234567')
            ->assertSee('onclick="showPrice', false)
            ->assertSee('onclick="hidePrice', false)
            ->assertDontSee('rejection')
            ->assertSee('No receipt');

        $this->get(route('product_price.show', 999999))->assertNotFound();
    }

    public function test_show_offers_only_the_actions_that_fit_the_status(): void
    {
        // مخفي: بس إظهار
        $rejected = $this->priceWithStatus('rejected');

        $this->get(route('product_price.show', $rejected->id))
            ->assertOk()
            ->assertSee('onclick="showPrice', false)
            ->assertDontSee('onclick="hidePrice', false);

        // ظاهر: بس إخفاء
        $approved = $this->priceWithStatus('approved');

        $this->get(route('product_price.show', $approved->id))
            ->assertOk()
            ->assertDontSee('onclick="showPrice', false)
            ->assertSee('onclick="hidePrice', false);

        $flagged = $this->priceWithStatus('flagged', ['report_count' => 6]);

        $this->get(route('product_price.show', $flagged->id))
            ->assertOk()
            ->assertSee('onclick="showPrice', false)
            ->assertSee('onclick="hidePrice', false);
    }

    public function test_show_lists_who_confirmed_and_who_reported(): void
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser('Owner');
        $price = $this->makePrice($this->makeProduct($lookups, $owner), $lookups, $owner, 'approved', [
            'confirm_count' => 2,
            'report_count' => 1,
        ]);

        $this->makeVote($price, $this->makeUser('مؤكد-أول'), 'confirm');
        $this->makeVote($price, $this->makeUser('مؤكد-ثاني'), 'confirm');
        $this->makeVote($price, $this->makeUser('مبلّغ-وحيد'), 'report');

        $response = $this->get(route('product_price.show', $price->id))->assertOk();

        $response->assertSeeInOrder(['Confirmed by (2)', 'مؤكد-أول', 'مؤكد-ثاني', 'Reported by (1)', 'مبلّغ-وحيد']);
    }

    public function test_show_page_has_a_fixed_number_of_queries_regardless_of_vote_count(): void
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser();
        $price = $this->makePrice($this->makeProduct($lookups, $owner), $lookups, $owner);

        $count = function () use ($price) {
            \DB::flushQueryLog();
            \DB::enableQueryLog();
            $this->get(route('product_price.show', $price->id))->assertOk();

            return count(\DB::getQueryLog());
        };

        $this->makeVote($price, $this->makeUser(), 'confirm');
        $few = $count();

        foreach (range(1, 5) as $i) {
            $this->makeVote($price, $this->makeUser(), 'report');
        }

        $this->assertSame($few, $count());
    }

    public function test_receipt_is_served_from_the_private_disk(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/test-receipt.jpg', 'fake-image-bytes');

        $price = $this->priceWithStatus('pending', ['product_price_receipt_image' => 'receipts/test-receipt.jpg']);

        $this->get(route('product_price_receipt', $price->id))->assertOk();
    }

    public function test_receipt_returns_404_when_missing(): void
    {
        Storage::fake('local');

        $without = $this->priceWithStatus('pending');
        $dangling = $this->priceWithStatus('pending', ['product_price_receipt_image' => 'receipts/gone.jpg']);

        $this->get(route('product_price_receipt', $without->id))->assertNotFound();
        $this->get(route('product_price_receipt', $dangling->id))->assertNotFound();
    }

    // ---------- الإظهار ----------

    public function test_show_action_moves_a_pending_price_to_approved(): void
    {
        $price = $this->priceWithStatus('pending');

        $this->postJson(route('product_price_approve', $price->id))
            ->assertOk()
            ->assertJsonStructure(['redirect']);

        $price->refresh();
        $this->assertSame('approved', $price->product_price_status);
        $this->assertNotNull($price->reviewed_at);
    }

    public function test_an_admin_hidden_price_can_be_shown_again(): void
    {
        $price = $this->priceWithStatus('rejected');

        $this->postJson(route('product_price_approve', $price->id))->assertOk();

        $this->assertSame('approved', $price->refresh()->product_price_status);
        $this->getJson('/api/prices')->assertJsonCount(1, 'data');
    }

    public function test_show_action_is_refused_for_an_already_visible_price(): void
    {
        $price = $this->priceWithStatus('approved');

        $this->postJson(route('product_price_approve', $price->id))
            ->assertStatus(409)
            ->assertJson(['icon' => 'error']);

        $this->postJson(route('product_price_approve', 999999))->assertNotFound();
    }

    public function test_showing_a_flagged_price_dismisses_its_reports(): void
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser();
        $price = $this->makePrice($this->makeProduct($lookups, $owner), $lookups, $owner, 'flagged', [
            'confirm_count' => 1,
            'report_count' => 6,
        ]);

        $confirmer = $this->makeUser();
        $this->makeVote($price, $confirmer, 'confirm');
        foreach (range(1, 6) as $i) {
            $this->makeVote($price, $this->makeUser(), 'report');
        }

        $this->postJson(route('product_price_approve', $price->id))->assertOk();

        $price->refresh();
        $this->assertSame('approved', $price->product_price_status);
        $this->assertSame(0, $price->report_count);
        $this->assertSame(1, $price->confirm_count);
        $this->assertSame(0, PriceVote::where('price_vote_type', 'report')->count());
        // التأكيدات بتضل
        $this->assertSame(1, PriceVote::where('price_vote_type', 'confirm')->count());

        // ومعها بيظهر بالـ feed من جديد
        $this->getJson('/api/prices')->assertJsonCount(1, 'data');
    }

    // ---------- الإخفاء (رفض بدون سبب) ----------

    public function test_hide_needs_no_reason_and_records_the_review_time(): void
    {
        $price = $this->priceWithStatus('pending');

        $this->postJson(route('product_price_reject', $price->id))
            ->assertOk()
            ->assertJsonStructure(['redirect']);

        $price->refresh();
        $this->assertSame('rejected', $price->product_price_status);
        $this->assertNotNull($price->reviewed_at);
    }

    public function test_hide_works_from_pending_approved_and_flagged_but_not_twice(): void
    {
        foreach (['pending', 'approved', 'flagged'] as $status) {
            $price = $this->priceWithStatus($status);

            $this->postJson(route('product_price_reject', $price->id))->assertOk();
            $this->assertSame('rejected', $price->refresh()->product_price_status);

            $this->postJson(route('product_price_reject', $price->id))->assertStatus(409);
        }

        $this->postJson(route('product_price_reject', 999999))->assertNotFound();
    }

    public function test_hidden_price_never_reaches_the_public_feed(): void
    {
        $price = $this->priceWithStatus('approved');
        $this->getJson('/api/prices')->assertJsonCount(1, 'data');

        $this->postJson(route('product_price_reject', $price->id))->assertOk();

        $this->getJson('/api/prices')->assertJsonCount(0, 'data');
    }

    // ---------- الحذف ----------

    public function test_destroy_removes_the_price_its_votes_and_its_receipt(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('receipts/to-delete.jpg', 'bytes');

        $lookups = $this->lookups();
        $owner = $this->makeUser();
        $product = $this->makeProduct($lookups, $owner);
        $price = $this->makePrice($product, $lookups, $owner, 'approved', ['product_price_receipt_image' => 'receipts/to-delete.jpg']);
        $keep = $this->makePrice($product, $lookups, $owner, 'approved');
        $this->makeVote($price, $this->makeUser(), 'confirm');

        $this->deleteJson(route('product_price.destroy', $price->id))
            ->assertOk()
            ->assertJson(['icon' => 'success']);

        $this->assertNull(ProductPrice::find($price->id));
        $this->assertSame(0, PriceVote::count());
        Storage::disk('local')->assertMissing('receipts/to-delete.jpg');
        // باقي الأسعار والمنتج بيضلوا
        $this->assertNotNull(ProductPrice::find($keep->id));
        $this->assertNotNull($product->fresh());

        $this->deleteJson(route('product_price.destroy', $price->id))->assertNotFound();
    }
}