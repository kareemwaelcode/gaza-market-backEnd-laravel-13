<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsMarketData;
use Tests\TestCase;

/**
 * حماية من N+1 بمسارات الفرونت العامة (السوق والمحلات).
 *
 * الفكرة: نطلب المسار ببيانات قليلة ثم بكثيرة، وعدد الاستعلامات لازم يبقى ثابت.
 * ملاحظة: الاختبار على SQLite داخل الذاكرة، فهو بيثبت عدد الاستعلامات مش سرعتها على MySQL.
 */
class QueryCountMarketPlacesTest extends TestCase
{
    use BuildsMarketData;
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

    public function test_market_feed_query_count_does_not_grow_with_rows(): void
    {
        $owner = $this->makeUser();
        $this->makeListing($owner);
        $this->makeListing($owner);

        $few = $this->queryCount('/api/market');

        for ($i = 0; $i < 10; $i++) {
            // كل إعلان بمنطقة وتصنيف مختلفين: لو العلاقات مش محمّلة مسبقًا العدد بيزيد.
            $this->makeListing($owner);
        }

        $many = $this->queryCount('/api/market');

        // تأكيد إن الاختبار فعلاً شاف الـ 12 صف (مش رد فاضي)
        Cache::flush();
        $this->getJson('/api/market')->assertOk()->assertJsonCount(12, 'data');

        $this->assertSame($few, $many);
    }

    public function test_market_detail_query_count_does_not_grow_with_images(): void
    {
        $owner = $this->makeUser();
        $listing = $this->makeListing($owner);

        $one = $this->queryCount('/api/market/'.$listing->id);

        for ($i = 2; $i <= 6; $i++) {
            $listing->images()->create([
                'listing_image_path' => 'https://example.com/'.$listing->id.'-'.$i.'.jpg',
                'listing_image_sort_order' => $i,
            ]);
        }

        $many = $this->queryCount('/api/market/'.$listing->id);

        $this->assertSame($one, $many);
    }

    public function test_places_feed_query_count_does_not_grow_with_rows(): void
    {
        $this->makePlace($this->makeUser());
        $this->makePlace($this->makeUser());

        $few = $this->queryCount('/api/places');

        for ($i = 0; $i < 10; $i++) {
            // user_id فريد بجدول places، فكل محل بمالك مختلف.
            $this->makePlace($this->makeUser());
        }

        $many = $this->queryCount('/api/places');

        Cache::flush();
        $this->getJson('/api/places')->assertOk()->assertJsonCount(12, 'data');

        $this->assertSame($few, $many);
    }
}