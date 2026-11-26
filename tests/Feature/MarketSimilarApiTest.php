<?php

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketData;
use Tests\TestCase;

class MarketSimilarApiTest extends TestCase
{
    use BuildsMarketData;
    use RefreshDatabase;

    private function ids(string $url): array
    {
        return array_column($this->getJson($url)->assertOk()->json('data'), 'id');
    }

    public function test_guest_gets_approved_listings_of_the_same_category_without_the_listing_itself(): void
    {
        $owner = $this->makeUser();
        $category = $this->makeListingCategory();
        $area = $this->makeArea();

        $base = $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'area' => $area]);
        $similar = $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'area' => $area]);
        $this->makeListing($owner, Listing::STATUS_APPROVED); // تصنيف تاني
        $this->makeListing($owner, Listing::STATUS_PENDING, ['category' => $category, 'area' => $area]);
        $this->makeListing($owner, Listing::STATUS_REJECTED, ['category' => $category, 'area' => $area]);
        $this->makeListing($owner, Listing::STATUS_SOLD, ['category' => $category, 'area' => $area]);

        $this->assertSame([$similar->id], $this->ids('/api/market/'.$base->id.'/similar'));
    }

    public function test_same_area_comes_first_then_newest(): void
    {
        $owner = $this->makeUser();
        $category = $this->makeListingCategory();
        $area = $this->makeArea();

        $base = $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'area' => $area]);
        $otherAreaNew = $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'published_at' => now()->subMinute()]);
        $sameAreaOld = $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'area' => $area, 'published_at' => now()->subDay()]);
        $sameAreaNew = $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'area' => $area, 'published_at' => now()->subHour()]);

        $this->assertSame(
            [$sameAreaNew->id, $sameAreaOld->id, $otherAreaNew->id],
            $this->ids('/api/market/'.$base->id.'/similar')
        );
    }

    public function test_limit_is_applied_and_validated(): void
    {
        $owner = $this->makeUser();
        $category = $this->makeListingCategory();
        $area = $this->makeArea();

        $base = $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'area' => $area]);
        foreach (range(1, 3) as $i) {
            $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'area' => $area]);
        }

        $this->assertCount(2, $this->ids('/api/market/'.$base->id.'/similar?limit=2'));
        $this->getJson('/api/market/'.$base->id.'/similar?limit=999')->assertStatus(400);
        $this->getJson('/api/market/'.$base->id.'/similar?limit=0')->assertStatus(400);
    }

    public function test_unknown_or_not_public_listing_returns_404(): void
    {
        $owner = $this->makeUser();

        $pending = $this->makeListing($owner, Listing::STATUS_PENDING);

        $this->getJson('/api/market/'.$pending->id.'/similar')->assertNotFound();
        $this->getJson('/api/market/999999/similar')->assertNotFound();
    }

    public function test_only_card_fields_are_returned_without_contact_data(): void
    {
        $owner = $this->makeUser();
        $category = $this->makeListingCategory();
        $area = $this->makeArea();

        $base = $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'area' => $area]);
        $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'area' => $area]);

        $item = $this->getJson('/api/market/'.$base->id.'/similar')->assertOk()->json('data.0');

        $this->assertSame(
            ['area', 'category', 'condition', 'cover', 'id', 'is_negotiable', 'price', 'published_at', 'region', 'title'],
            collect($item)->keys()->sort()->values()->all()
        );
        $this->assertArrayNotHasKey('phone', $item);
        $this->assertArrayNotHasKey('whatsapp', $item);
    }

    public function test_similar_cache_is_refreshed_when_a_new_listing_is_approved(): void
    {
        $owner = $this->makeUser();
        $category = $this->makeListingCategory();
        $area = $this->makeArea();

        $base = $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'area' => $area]);
        $this->assertCount(0, $this->ids('/api/market/'.$base->id.'/similar'));

        $this->makeListing($owner, Listing::STATUS_APPROVED, ['category' => $category, 'area' => $area]);

        $this->assertCount(1, $this->ids('/api/market/'.$base->id.'/similar'));
    }
}