<?php

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketData;
use Tests\TestCase;

class MarketPublicApiTest extends TestCase
{
    use BuildsMarketData;
    use RefreshDatabase;

    public function test_feed_lists_only_approved_listings_without_phone(): void
    {
        $owner = $this->makeUser();
        $approved = $this->makeListing($owner, Listing::STATUS_APPROVED);
        $this->makeListing($owner, Listing::STATUS_PENDING);
        $this->makeListing($owner, Listing::STATUS_REJECTED);
        $this->makeListing($owner, Listing::STATUS_SOLD);

        $response = $this->getJson('/api/market');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $approved->id)
            ->assertJsonPath('data.0.cover', 'https://example.com/'.$approved->id.'.jpg')
            ->assertJsonMissingPath('data.0.phone')
            ->assertJsonMissingPath('data.0.whatsapp')
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_feed_is_ordered_by_latest_published(): void
    {
        $owner = $this->makeUser();
        $older = $this->makeListing($owner, Listing::STATUS_APPROVED, ['published_at' => now()->subDay()]);
        $newer = $this->makeListing($owner, Listing::STATUS_APPROVED, ['published_at' => now()]);

        $this->getJson('/api/market')
            ->assertJsonPath('data.0.id', $newer->id)
            ->assertJsonPath('data.1.id', $older->id);
    }

    public function test_feed_filters_by_region_category_and_condition(): void
    {
        $owner = $this->makeUser();
        $areaA = $this->makeArea();
        $areaB = $this->makeArea();
        $categoryA = $this->makeListingCategory();
        $categoryB = $this->makeListingCategory();

        $target = $this->makeListing($owner, Listing::STATUS_APPROVED, [
            'area' => $areaA,
            'category' => $categoryA,
            'condition' => 'new',
        ]);
        $this->makeListing($owner, Listing::STATUS_APPROVED, ['area' => $areaB, 'category' => $categoryA, 'condition' => 'new']);
        $this->makeListing($owner, Listing::STATUS_APPROVED, ['area' => $areaA, 'category' => $categoryB, 'condition' => 'new']);
        $this->makeListing($owner, Listing::STATUS_APPROVED, ['area' => $areaA, 'category' => $categoryA, 'condition' => 'used']);

        $query = http_build_query([
            'region' => $areaA->area_slug,
            'category' => $categoryA->listing_category_slug,
            'condition' => 'new',
        ]);

        $this->getJson('/api/market?'.$query)
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $target->id);
    }

    public function test_feed_returns_empty_for_unknown_region_or_category(): void
    {
        $this->makeListing($this->makeUser());

        $this->getJson('/api/market?region=nope')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/market?category=nope')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_feed_rejects_invalid_parameters(): void
    {
        $this->getJson('/api/market?condition=broken')->assertStatus(400)->assertJsonPath('icon', 'error');
        $this->getJson('/api/market?limit=1000')->assertStatus(400);
        $this->getJson('/api/market?page=0')->assertStatus(400);
    }

    public function test_feed_paginates_with_has_more(): void
    {
        $owner = $this->makeUser();
        $this->makeListing($owner, Listing::STATUS_APPROVED, ['published_at' => now()->subMinute()]);
        $this->makeListing($owner, Listing::STATUS_APPROVED, ['published_at' => now()]);

        $this->getJson('/api/market?limit=1')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.has_more', true)
            ->assertJsonPath('meta.per_page', 1);

        $this->getJson('/api/market?limit=1&page=2')
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_public_routes_do_not_set_cookies(): void
    {
        $listing = $this->makeListing($this->makeUser());

        $feed = $this->getJson('/api/market');
        $detail = $this->getJson('/api/market/'.$listing->id);

        $this->assertSame([], $feed->headers->getCookies());
        $this->assertSame([], $detail->headers->getCookies());
    }

    public function test_feed_cache_is_invalidated_when_status_changes(): void
    {
        $owner = $this->makeUser();
        $pending = $this->makeListing($owner, Listing::STATUS_PENDING);

        $this->getJson('/api/market')->assertJsonCount(0, 'data');

        $pending->listing_status = Listing::STATUS_APPROVED;
        $pending->listing_published_at = now();
        $pending->save();

        $this->getJson('/api/market')->assertJsonCount(1, 'data');

        $pending->listing_status = Listing::STATUS_SOLD;
        $pending->save();

        $this->getJson('/api/market')->assertJsonCount(0, 'data');
    }

    public function test_detail_returns_contact_data_for_approved_listing(): void
    {
        $owner = $this->makeUser('Seller Name');
        $listing = $this->makeListing($owner, Listing::STATUS_APPROVED);

        $this->getJson('/api/market/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.id', $listing->id)
            ->assertJsonPath('data.phone', '0591234567')
            ->assertJsonPath('data.seller_name', 'Seller Name')
            ->assertJsonCount(1, 'data.images');
    }

    public function test_detail_hides_seller_name_for_deleted_user(): void
    {
        $owner = $this->makeUser('Seller Name');
        $listing = $this->makeListing($owner, Listing::STATUS_APPROVED);
        $owner->delete();

        $this->getJson('/api/market/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.seller_name', null);
    }

    public function test_detail_is_not_found_for_non_approved_or_missing_listings(): void
    {
        $owner = $this->makeUser();

        foreach ([Listing::STATUS_PENDING, Listing::STATUS_REJECTED, Listing::STATUS_SOLD] as $status) {
            $listing = $this->makeListing($owner, $status);

            $this->getJson('/api/market/'.$listing->id)
                ->assertStatus(404)
                ->assertJsonPath('icon', 'error');
        }

        $this->getJson('/api/market/999999')->assertStatus(404);
    }

    public function test_detail_rejects_non_numeric_ids(): void
    {
        $this->getJson('/api/market/abc')->assertStatus(404);
    }
}