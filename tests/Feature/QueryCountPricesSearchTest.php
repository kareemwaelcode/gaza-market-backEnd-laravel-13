<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsProductPrices;
use Tests\TestCase;

/**
 * حماية من N+1 بمسارات الأسعار والبحث العامة + تثبيت سلوك البحث (throttle والكاش).
 *
 * ملاحظة: الاختبار على SQLite داخل الذاكرة، فهو بيثبت عدد الاستعلامات مش سرعتها على MySQL.
 */
class QueryCountPricesSearchTest extends TestCase
{
    use BuildsProductPrices;
    use RefreshDatabase;

    /**
     * عدد الاستعلامات لطلب واحد بدون كاش.
     * الطلب الأول (تسخين) بيطلع الاستعلامات اللي مش مرتبطة بالبيانات، وبعدها بنفرّغ الكاش ونقيس.
     */
    private function queryCount(string $uri): int
    {
        $this->getJson($uri)->assertOk();

        Cache::flush();
        DB::flushQueryLog();
        DB::enableQueryLog();

        $this->getJson($uri)->assertOk();

        $count = count(DB::getQueryLog());
        DB::disableQueryLog();

        return $count;
    }

    // كل مرة lookups() جديدة: منطقة وتصنيف ووحدة مختلفين، فأي علاقة مش محمّلة مسبقًا بتزيد العدد.
    private function addFeedRow(int $i): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct($this->lookups(), $user, 'active', [
            'product_name_ar' => 'سكر '.$i,
            'product_name_en' => 'Sugar '.$i,
        ]);

        $this->makePrice($product, $this->lookups(), $user);
    }

    public function test_prices_feed_query_count_does_not_grow_with_rows(): void
    {
        $this->addFeedRow(1);
        $this->addFeedRow(2);

        $few = $this->queryCount('/api/prices');

        for ($i = 3; $i <= 12; $i++) {
            $this->addFeedRow($i);
        }

        $many = $this->queryCount('/api/prices');

        // تأكيد إن الاختبار فعلاً شاف الـ 12 صف (مش رد فاضي)
        Cache::flush();
        $this->getJson('/api/prices')->assertOk()->assertJsonCount(12, 'data');

        $this->assertSame($few, $many);
    }

    public function test_price_history_query_count_does_not_grow_with_rows(): void
    {
        $user = $this->makeUser();
        $product = $this->makeProduct($this->lookups(), $user);

        $this->makePrice($product, $this->lookups(), $user);
        $this->makePrice($product, $this->lookups(), $user);

        $few = $this->queryCount('/api/products/'.$product->id.'/prices');

        for ($i = 0; $i < 10; $i++) {
            // كل سعر بمنطقة مختلفة
            $this->makePrice($product, $this->lookups(), $user);
        }

        $many = $this->queryCount('/api/products/'.$product->id.'/prices');

        Cache::flush();
        $this->getJson('/api/products/'.$product->id.'/prices')->assertOk()->assertJsonCount(12, 'data');

        $this->assertSame($few, $many);
    }

    public function test_product_search_query_count_does_not_grow_with_rows(): void
    {
        $user = $this->makeUser();

        for ($i = 1; $i <= 2; $i++) {
            $this->makeProduct($this->lookups(), $user, 'active', [
                'product_name_ar' => 'سكر '.$i,
                'product_name_en' => 'Sugar '.$i,
            ]);
        }

        $few = $this->queryCount('/api/products/search?q=sugar');

        for ($i = 3; $i <= 12; $i++) {
            // اسم مختلف لكل منتج، فكل واحد بيطلع بنتيجة لحاله (البحث بيجمّع بالاسم والكمية والوحدة)
            $this->makeProduct($this->lookups(), $user, 'active', [
                'product_name_ar' => 'سكر '.$i,
                'product_name_en' => 'Sugar '.$i,
            ]);
        }

        $many = $this->queryCount('/api/products/search?q=sugar');

        Cache::flush();
        $this->getJson('/api/products/search?q=sugar')->assertOk()->assertJsonCount(12, 'data');

        $this->assertSame($few, $many);
    }

    // ---------- سلوك البحث: كاش و throttle (بدون تغيير بالـ API) ----------

    public function test_product_search_is_served_from_cache_on_the_second_request(): void
    {
        $this->makeProduct($this->lookups(), $this->makeUser());

        $this->getJson('/api/products/search?q=sugar')->assertOk()->assertJsonCount(1, 'data');

        DB::flushQueryLog();
        DB::enableQueryLog();

        // نفس الكلمة بحروف مختلفة: بتتوحّد (lowercase + trim) فبتطلع من الكاش
        $this->getJson('/api/products/search?q=SUGAR')->assertOk()->assertJsonCount(1, 'data');

        $this->assertCount(0, DB::getQueryLog());
    }

    public function test_product_search_is_rate_limited_per_ip(): void
    {
        $this->makeProduct($this->lookups(), $this->makeUser());

        // الحد 30 طلب/دقيقة لكل IP (search-product بـ AppServiceProvider)
        for ($i = 0; $i < 30; $i++) {
            $this->getJson('/api/products/search?q=sugar')->assertOk();
        }

        $this->getJson('/api/products/search?q=sugar')
            ->assertStatus(429)
            ->assertHeader('Retry-After');
    }

    public function test_product_search_rejects_a_term_shorter_than_two_characters(): void
    {
        $this->getJson('/api/products/search?q=s')
            ->assertStatus(400)
            ->assertJsonPath('icon', 'error');
    }
}