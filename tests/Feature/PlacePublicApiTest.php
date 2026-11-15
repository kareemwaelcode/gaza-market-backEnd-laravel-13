<?php

namespace Tests\Feature;

use App\Models\Place;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsMarketData;
use Tests\TestCase;

class PlacePublicApiTest extends TestCase
{
    use BuildsMarketData;
    use RefreshDatabase;

    public function test_feed_lists_only_approved_places_without_contact_data(): void
    {
        $approved = $this->makePlace($this->makeUser(), Place::STATUS_APPROVED);
        $this->makePlace($this->makeUser(), Place::STATUS_PENDING);
        $this->makePlace($this->makeUser(), Place::STATUS_REJECTED);

        $response = $this->getJson('/api/places');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $approved->place_slug)
            ->assertJsonMissingPath('data.0.phone')
            ->assertJsonMissingPath('data.0.whatsapp')
            ->assertJsonPath('meta.has_more', false);
    }

    public function test_feed_is_newest_approved_first(): void
    {
        $older = $this->makePlace($this->makeUser(), Place::STATUS_APPROVED, ['approved_at' => now()->subDay()]);
        $newer = $this->makePlace($this->makeUser(), Place::STATUS_APPROVED, ['approved_at' => now()]);

        $this->getJson('/api/places')
            ->assertJsonPath('data.0.slug', $newer->place_slug)
            ->assertJsonPath('data.1.slug', $older->place_slug);
    }

    public function test_feed_filters_by_region_and_type(): void
    {
        $areaA = $this->makeArea();
        $areaB = $this->makeArea();

        $target = $this->makePlace($this->makeUser(), Place::STATUS_APPROVED, ['area' => $areaA, 'type' => 'restaurant']);
        $this->makePlace($this->makeUser(), Place::STATUS_APPROVED, ['area' => $areaB, 'type' => 'restaurant']);
        $this->makePlace($this->makeUser(), Place::STATUS_APPROVED, ['area' => $areaA, 'type' => 'cafe']);

        $this->getJson('/api/places?region='.$areaA->area_slug.'&type=restaurant')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.slug', $target->place_slug);

        $this->getJson('/api/places?region=nope')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/places?category=nope')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_feed_rejects_invalid_parameters(): void
    {
        $this->getJson('/api/places?type=bank')->assertStatus(400)->assertJsonPath('icon', 'error');
        $this->getJson('/api/places?limit=500')->assertStatus(400);
    }

    public function test_detail_returns_contact_data_for_approved_place(): void
    {
        $place = $this->makePlace($this->makeUser(), Place::STATUS_APPROVED);

        $this->getJson('/api/places/'.$place->place_slug)
            ->assertOk()
            ->assertJsonPath('data.slug', $place->place_slug)
            ->assertJsonPath('data.phone', $place->place_phone)
            ->assertJsonPath('data.whatsapp', $place->place_whatsapp);
    }

    public function test_detail_is_not_found_for_non_approved_or_unknown_places(): void
    {
        $pending = $this->makePlace($this->makeUser(), Place::STATUS_PENDING);
        $rejected = $this->makePlace($this->makeUser(), Place::STATUS_REJECTED);

        $this->getJson('/api/places/'.$pending->place_slug)->assertStatus(404);
        $this->getJson('/api/places/'.$rejected->place_slug)->assertStatus(404);
        $this->getJson('/api/places/p-aaaaaaaaaaaa')->assertStatus(404);
        $this->getJson('/api/places/not-a-slug')->assertStatus(404);
    }

    public function test_mine_route_is_still_protected_and_not_swallowed_by_slug_route(): void
    {
        $this->getJson('/api/places/mine')->assertStatus(401);
    }

    public function test_public_place_routes_do_not_set_cookies(): void
    {
        $place = $this->makePlace($this->makeUser(), Place::STATUS_APPROVED);

        $this->assertSame([], $this->getJson('/api/places')->headers->getCookies());
        $this->assertSame([], $this->getJson('/api/places/'.$place->place_slug)->headers->getCookies());
    }

    public function test_cache_is_invalidated_when_place_status_or_data_changes(): void
    {
        $place = $this->makePlace($this->makeUser(), Place::STATUS_PENDING);

        $this->getJson('/api/places')->assertJsonCount(0, 'data');

        $place->place_status = Place::STATUS_APPROVED;
        $place->place_approved_at = now();
        $place->save();

        $this->getJson('/api/places')->assertJsonCount(1, 'data');
        $this->getJson('/api/places/'.$place->place_slug)->assertJsonPath('data.is_open', true);

        $place->place_is_open = false;
        $place->save();

        $this->getJson('/api/places/'.$place->place_slug)->assertJsonPath('data.is_open', false);

        $place->place_status = Place::STATUS_REJECTED;
        $place->place_approved_at = null;
        $place->save();

        $this->getJson('/api/places')->assertJsonCount(0, 'data');
        $this->getJson('/api/places/'.$place->place_slug)->assertStatus(404);
    }
}