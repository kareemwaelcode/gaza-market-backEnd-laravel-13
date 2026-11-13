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
            ->assertSee('Approve')
            ->assertSee('Reject')
            ->assertSee('No receipt');

        $this->get(route('product_price.show', 999999))->assertNotFound();
    }

    public function test_show_offers_only_the_actions_that_fit_the_status(): void
    {
        $rejected = $this->priceWithStatus('rejected', ['rejection_reason' => 'سعر غير منطقي']);

        $this->get(route('product_price.show', $rejected->id))
            ->assertOk()
            ->assertSee('سعر غير منطقي')
            ->assertDontSee('onclick="approvePrice')
            ->assertDontSee('onclick="rejectPrice');

        $flagged = $this->priceWithStatus('flagged', ['report_count' => 4]);

        $this->get(route('product_price.show', $flagged->id))
            ->assertOk()
            ->assertSee('onclick="approvePrice', false)
            ->assertSee('onclick="rejectPrice', false);
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

    // ---------- الموافقة ----------

    public function test_approve_moves_a_pending_price_to_approved(): void
    {
        $price = $this->priceWithStatus('pending', ['rejection_reason' => 'قديم']);

        $this->postJson(route('product_price_approve', $price->id))
            ->assertOk()
            ->assertJsonStructure(['redirect']);

        $price->refresh();
        $this->assertSame('approved', $price->product_price_status);
        $this->assertNotNull($price->reviewed_at);
        $this->assertNull($price->rejection_reason);
    }

    public function test_approve_is_only_allowed_from_pending_or_flagged(): void
    {
        foreach (['approved', 'rejected'] as $status) {
            $price = $this->priceWithStatus($status);

            $this->postJson(route('product_price_approve', $price->id))
                ->assertStatus(409)
                ->assertJson(['icon' => 'error']);

            $this->assertSame($status, $price->refresh()->product_price_status);
        }

        $this->postJson(route('product_price_approve', 999999))->assertNotFound();
    }

    public function test_approving_a_flagged_price_dismisses_its_reports(): void
    {
        $lookups = $this->lookups();
        $owner = $this->makeUser();
        $price = $this->makePrice($this->makeProduct($lookups, $owner), $lookups, $owner, 'flagged', [
            'confirm_count' => 1,
            'report_count' => 4,
        ]);

        $confirmer = $this->makeUser();
        $this->makeVote($price, $confirmer, 'confirm');
        foreach (range(1, 4) as $i) {
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

    // ---------- الرفض ----------

    public function test_reject_requires_a_reason(): void
    {
        $price = $this->priceWithStatus('pending');

        foreach ([[], ['reason' => ''], ['reason' => str_repeat('a', 256)]] as $payload) {
            $this->postJson(route('product_price_reject', $price->id), $payload)
                ->assertStatus(400)
                ->assertJson(['icon' => 'error']);
        }

        $this->assertSame('pending', $price->refresh()->product_price_status);
    }

    public function test_reject_stores_the_reason_and_review_time(): void
    {
        $price = $this->priceWithStatus('pending');

        $this->postJson(route('product_price_reject', $price->id), ['reason' => 'الفاتورة لا تطابق السعر'])
            ->assertOk()
            ->assertJsonStructure(['redirect']);

        $price->refresh();
        $this->assertSame('rejected', $price->product_price_status);
        $this->assertSame('الفاتورة لا تطابق السعر', $price->rejection_reason);
        $this->assertNotNull($price->reviewed_at);
    }

    public function test_reject_works_from_pending_approved_and_flagged_but_not_twice(): void
    {
        foreach (['pending', 'approved', 'flagged'] as $status) {
            $price = $this->priceWithStatus($status);

            $this->postJson(route('product_price_reject', $price->id), ['reason' => 'x'])->assertOk();
            $this->assertSame('rejected', $price->refresh()->product_price_status);

            $this->postJson(route('product_price_reject', $price->id), ['reason' => 'y'])->assertStatus(409);
            $this->assertSame('x', $price->refresh()->rejection_reason);
        }

        $this->postJson(route('product_price_reject', 999999), ['reason' => 'x'])->assertNotFound();
    }

    public function test_rejected_price_never_reaches_the_public_feed(): void
    {
        $price = $this->priceWithStatus('approved');
        $this->getJson('/api/prices')->assertJsonCount(1, 'data');

        $this->postJson(route('product_price_reject', $price->id), ['reason' => 'x'])->assertOk();

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