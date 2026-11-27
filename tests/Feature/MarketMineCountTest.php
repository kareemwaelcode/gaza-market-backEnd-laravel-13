<?php

namespace Tests\Feature;

use App\Models\Listing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketData;
use Tests\TestCase;

class MarketMineCountTest extends TestCase
{
    use BuildsMarketData;
    use RefreshDatabase;

    public function test_guests_cannot_read_the_count(): void
    {
        $this->getJson('/api/market/mine/count')->assertStatus(401);
    }

    public function test_count_includes_only_own_pending_and_approved_listings(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $this->makeListing($user, Listing::STATUS_PENDING);
        $this->makeListing($user, Listing::STATUS_APPROVED);
        $this->makeListing($user, Listing::STATUS_APPROVED);
        $this->makeListing($user, Listing::STATUS_REJECTED);
        $this->makeListing($user, Listing::STATUS_SOLD);
        $this->makeListing($other, Listing::STATUS_APPROVED);

        $this->actingAs($user)->getJson('/api/market/mine/count')
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.pending', 1)
            ->assertJsonPath('data.approved', 2);
    }

    public function test_count_is_zero_when_the_user_has_no_listings(): void
    {
        $this->actingAs($this->makeUser())->getJson('/api/market/mine/count')
            ->assertOk()
            ->assertJsonPath('data.total', 0);
    }

    public function test_profile_page_is_served_with_the_listings_tile(): void
    {
        $this->get('/gazamarket/profile')
            ->assertOk()
            ->assertSee('id="userListingsCount"', false)
            ->assertDontSee('href="profile.html"', false);
    }
}