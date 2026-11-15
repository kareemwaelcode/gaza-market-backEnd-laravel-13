<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMarketData;
use Tests\TestCase;

class MarketOwnerTest extends TestCase
{
    use BuildsMarketData;
    use RefreshDatabase;

    private function payload(Listing $listing, array $overrides = []): array
    {
        return array_merge([
            'listing_title' => 'Updated title',
            'listing_condition' => 'new',
            'listing_description' => 'Updated description',
            'listing_price' => 200,
            'listing_phone' => '0591112223',
            'listing_category_id' => $listing->listing_category_id,
            'area_id' => $listing->area_id,
        ], $overrides);
    }

    public function test_guests_are_rejected_on_owner_routes(): void
    {
        $listing = $this->makeListing($this->makeUser(), Listing::STATUS_PENDING);

        $this->getJson('/api/market/mine')->assertStatus(401);
        $this->getJson('/api/market/mine/'.$listing->id)->assertStatus(401);
        $this->patchJson('/api/market/'.$listing->id, [])->assertStatus(401);
        $this->deleteJson('/api/market/'.$listing->id)->assertStatus(401);
        $this->postJson('/api/market/'.$listing->id.'/sold')->assertStatus(401);
    }

    public function test_mine_lists_only_own_pending_and_approved_listings(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $pending = $this->makeListing($user, Listing::STATUS_PENDING);
        $approved = $this->makeListing($user, Listing::STATUS_APPROVED);
        $this->makeListing($user, Listing::STATUS_REJECTED);
        $this->makeListing($user, Listing::STATUS_SOLD);
        $this->makeListing($other, Listing::STATUS_APPROVED);

        $response = $this->actingAs($user)->getJson('/api/market/mine');

        $response->assertOk()->assertJsonCount(2, 'data');

        $ids = collect($response->json('data'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$pending->id, $approved->id], $ids);
        $response->assertJsonPath('data.0.phone', '0591234567');
    }

    public function test_show_mine_returns_own_pending_listing_and_hides_foreign_or_hidden_ones(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $pending = $this->makeListing($user, Listing::STATUS_PENDING);
        $rejected = $this->makeListing($user, Listing::STATUS_REJECTED);
        $foreign = $this->makeListing($other, Listing::STATUS_PENDING);

        $this->actingAs($user)->getJson('/api/market/mine/'.$pending->id)
            ->assertOk()
            ->assertJsonPath('data.status', Listing::STATUS_PENDING);

        $this->actingAs($user)->getJson('/api/market/mine/'.$rejected->id)->assertStatus(404);
        $this->actingAs($user)->getJson('/api/market/mine/'.$foreign->id)->assertStatus(404);
    }

    public function test_pending_listing_can_be_updated_and_stays_pending(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $listing = $this->makeListing($user, Listing::STATUS_PENDING);

        $this->actingAs($user)->post('/api/market/'.$listing->id, $this->payload($listing, ['listing_is_negotiable' => 1]), ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.status', Listing::STATUS_PENDING);

        $fresh = $listing->fresh();
        $this->assertSame('Updated title', $fresh->listing_title);
        $this->assertSame('new', $fresh->listing_condition);
        $this->assertTrue($fresh->listing_is_negotiable);
        $this->assertSame(Listing::STATUS_PENDING, $fresh->listing_status);
        $this->assertSame(1, ListingImage::where('listing_id', $listing->id)->count());
    }

    public function test_update_with_images_replaces_old_images_and_files(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $listing = $this->makeListing($user, Listing::STATUS_PENDING);

        ListingImage::where('listing_id', $listing->id)->delete();
        $oldPath = "listings/{$listing->id}/old.jpg";
        Storage::disk('public')->put($oldPath, 'old');
        $listing->images()->create(['listing_image_path' => $oldPath, 'listing_image_sort_order' => 1]);

        $payload = $this->payload($listing, [
            'images' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.png')],
        ]);

        $response = $this->actingAs($user)->post('/api/market/'.$listing->id, $payload, ['Accept' => 'application/json']);

        $response->assertOk()->assertJsonCount(2, 'data.images');

        $this->assertSame(2, ListingImage::where('listing_id', $listing->id)->count());
        $this->assertDatabaseMissing('listing_images', ['listing_image_path' => $oldPath]);
        Storage::disk('public')->assertMissing($oldPath);

        foreach (ListingImage::where('listing_id', $listing->id)->get() as $image) {
            Storage::disk('public')->assertExists($image->listing_image_path);
        }
    }

    public function test_update_is_rejected_for_approved_foreign_and_invalid_requests(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $other = $this->makeUser();

        $approved = $this->makeListing($user, Listing::STATUS_APPROVED);
        $foreign = $this->makeListing($other, Listing::STATUS_PENDING);
        $pending = $this->makeListing($user, Listing::STATUS_PENDING);

        $this->actingAs($user)->post('/api/market/'.$approved->id, $this->payload($approved), ['Accept' => 'application/json'])
            ->assertStatus(409);
        $this->assertNotSame('Updated title', $approved->fresh()->listing_title);

        $this->actingAs($user)->post('/api/market/'.$foreign->id, $this->payload($foreign), ['Accept' => 'application/json'])
            ->assertStatus(404);
        $this->assertNotSame('Updated title', $foreign->fresh()->listing_title);

        $this->actingAs($user)->post('/api/market/'.$pending->id, $this->payload($pending, ['listing_price' => 0]), ['Accept' => 'application/json'])
            ->assertStatus(400);
    }

    public function test_pending_listing_can_be_deleted_with_its_files(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $listing = $this->makeListing($user, Listing::STATUS_PENDING);

        ListingImage::where('listing_id', $listing->id)->delete();
        $path = "listings/{$listing->id}/photo.jpg";
        Storage::disk('public')->put($path, 'x');
        $listing->images()->create(['listing_image_path' => $path, 'listing_image_sort_order' => 1]);

        $this->actingAs($user)->deleteJson('/api/market/'.$listing->id)
            ->assertOk()
            ->assertJsonPath('data.deleted', true);

        $this->assertDatabaseMissing('listings', ['id' => $listing->id]);
        $this->assertDatabaseMissing('listing_images', ['listing_id' => $listing->id]);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_delete_is_rejected_for_approved_and_foreign_listings(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $approved = $this->makeListing($user, Listing::STATUS_APPROVED);
        $foreign = $this->makeListing($other, Listing::STATUS_PENDING);

        $this->actingAs($user)->deleteJson('/api/market/'.$approved->id)->assertStatus(409);
        $this->actingAs($user)->deleteJson('/api/market/'.$foreign->id)->assertStatus(404);

        $this->assertDatabaseHas('listings', ['id' => $approved->id]);
        $this->assertDatabaseHas('listings', ['id' => $foreign->id]);
    }

    public function test_approved_listing_can_be_marked_sold_and_leaves_the_market(): void
    {
        $user = $this->makeUser();
        $listing = $this->makeListing($user, Listing::STATUS_APPROVED);

        $this->getJson('/api/market')->assertJsonCount(1, 'data');
        $this->getJson('/api/market/'.$listing->id)->assertOk();

        $this->actingAs($user)->postJson('/api/market/'.$listing->id.'/sold')
            ->assertOk()
            ->assertJsonPath('data.status', Listing::STATUS_SOLD);

        $this->assertSame(Listing::STATUS_SOLD, $listing->fresh()->listing_status);
        $this->getJson('/api/market')->assertJsonCount(0, 'data');
        $this->getJson('/api/market/'.$listing->id)->assertStatus(404);
    }

    public function test_sold_is_rejected_for_pending_foreign_and_already_sold_listings(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        $pending = $this->makeListing($user, Listing::STATUS_PENDING);
        $sold = $this->makeListing($user, Listing::STATUS_SOLD);
        $foreign = $this->makeListing($other, Listing::STATUS_APPROVED);

        $this->actingAs($user)->postJson('/api/market/'.$pending->id.'/sold')->assertStatus(409);
        $this->actingAs($user)->postJson('/api/market/'.$sold->id.'/sold')->assertStatus(409);
        $this->actingAs($user)->postJson('/api/market/'.$foreign->id.'/sold')->assertStatus(404);

        $this->assertSame(Listing::STATUS_APPROVED, $foreign->fresh()->listing_status);
        $this->assertSame(Listing::STATUS_PENDING, $pending->fresh()->listing_status);
    }
}