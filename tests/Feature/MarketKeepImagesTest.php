<?php

namespace Tests\Feature;

use App\Models\Listing;
use App\Models\ListingImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsMarketData;
use Tests\TestCase;

class MarketKeepImagesTest extends TestCase
{
    use BuildsMarketData;
    use RefreshDatabase;

    private function pendingWithImages(int $count = 3): array
    {
        Storage::fake('public');

        $user = $this->makeUser();
        $listing = $this->makeListing($user, Listing::STATUS_PENDING);
        ListingImage::where('listing_id', $listing->id)->delete();

        $images = [];
        foreach (range(1, $count) as $i) {
            $path = "listings/{$listing->id}/old-{$i}.jpg";
            Storage::disk('public')->put($path, 'old'.$i);
            $images[] = $listing->images()->create(['listing_image_path' => $path, 'listing_image_sort_order' => $i]);
        }

        return [$user, $listing, $images];
    }

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

    private function send(Listing $listing, $user, array $payload)
    {
        return $this->actingAs($user)->post('/api/market/'.$listing->id, $payload, ['Accept' => 'application/json']);
    }

    public function test_kept_images_stay_removed_ones_are_deleted_and_order_is_renumbered(): void
    {
        [$user, $listing, $images] = $this->pendingWithImages(3);

        // بنحتفظ بالتانية والتالتة وبنحذف الأولى (الغلاف)
        $response = $this->send($listing, $user, $this->payload($listing, [
            'keep_images' => [$images[1]->url, $images[2]->url],
        ]));

        $response->assertOk()->assertJsonCount(2, 'data.images');

        $rows = ListingImage::where('listing_id', $listing->id)->orderBy('listing_image_sort_order')->get();
        $this->assertSame([1, 2], $rows->pluck('listing_image_sort_order')->all());
        $this->assertSame([$images[1]->listing_image_path, $images[2]->listing_image_path], $rows->pluck('listing_image_path')->all());

        Storage::disk('public')->assertMissing($images[0]->listing_image_path);
        Storage::disk('public')->assertExists($images[1]->listing_image_path);
        Storage::disk('public')->assertExists($images[2]->listing_image_path);
    }

    public function test_kept_images_can_be_combined_with_new_files(): void
    {
        [$user, $listing, $images] = $this->pendingWithImages(2);

        $response = $this->send($listing, $user, $this->payload($listing, [
            'keep_images' => [$images[1]->url],
            'images' => [UploadedFile::fake()->image('new.jpg')],
        ]));

        $response->assertOk()->assertJsonCount(2, 'data.images');

        $rows = ListingImage::where('listing_id', $listing->id)->orderBy('listing_image_sort_order')->get();
        $this->assertSame([1, 2], $rows->pluck('listing_image_sort_order')->all());
        $this->assertSame($images[1]->listing_image_path, $rows[0]->listing_image_path);
        Storage::disk('public')->assertExists($rows[1]->listing_image_path);
        Storage::disk('public')->assertMissing($images[0]->listing_image_path);
    }

    public function test_exceeding_three_images_is_rejected_and_nothing_changes(): void
    {
        [$user, $listing, $images] = $this->pendingWithImages(3);

        $this->send($listing, $user, $this->payload($listing, [
            'keep_images' => [$images[0]->url, $images[1]->url, $images[2]->url],
            'images' => [UploadedFile::fake()->image('extra.jpg')],
        ]))->assertStatus(400);

        $this->assertSame(3, ListingImage::where('listing_id', $listing->id)->count());
        $this->assertSame('Test item', $listing->fresh()->listing_title);
    }

    public function test_dropping_every_image_without_new_files_is_rejected(): void
    {
        [$user, $listing, $images] = $this->pendingWithImages(2);

        $this->send($listing, $user, $this->payload($listing, [
            'keep_images' => ['https://evil.example/not-mine.jpg'],
        ]))->assertStatus(400);

        $this->assertSame(2, ListingImage::where('listing_id', $listing->id)->count());
        Storage::disk('public')->assertExists($images[0]->listing_image_path);
        $this->assertSame('Test item', $listing->fresh()->listing_title);
    }

    public function test_unknown_urls_are_ignored_and_do_not_add_images(): void
    {
        [$user, $listing, $images] = $this->pendingWithImages(2);

        $this->send($listing, $user, $this->payload($listing, [
            'keep_images' => [$images[0]->url, 'https://evil.example/x.jpg'],
        ]))->assertOk()->assertJsonCount(1, 'data.images');

        $this->assertSame([$images[0]->listing_image_path], ListingImage::where('listing_id', $listing->id)->pluck('listing_image_path')->all());
    }

    public function test_without_keep_images_new_files_still_replace_everything(): void
    {
        [$user, $listing, $images] = $this->pendingWithImages(2);

        $this->send($listing, $user, $this->payload($listing, [
            'images' => [UploadedFile::fake()->image('only.jpg')],
        ]))->assertOk()->assertJsonCount(1, 'data.images');

        $this->assertSame(1, ListingImage::where('listing_id', $listing->id)->count());
        Storage::disk('public')->assertMissing($images[0]->listing_image_path);
        Storage::disk('public')->assertMissing($images[1]->listing_image_path);
    }

    public function test_foreign_user_cannot_change_images_of_someone_elses_listing(): void
    {
        [, $listing, $images] = $this->pendingWithImages(2);
        $stranger = $this->makeUser();

        $this->send($listing, $stranger, $this->payload($listing, ['keep_images' => [$images[0]->url]]))->assertNotFound();

        $this->assertSame(2, ListingImage::where('listing_id', $listing->id)->count());
    }

    public function test_approved_listing_images_cannot_be_changed(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $listing = $this->makeListing($user, Listing::STATUS_APPROVED);
        $url = $listing->images()->first()->url;

        $this->send($listing, $user, $this->payload($listing, ['keep_images' => [$url]]))->assertStatus(409);

        $this->assertSame(1, ListingImage::where('listing_id', $listing->id)->count());
    }
}