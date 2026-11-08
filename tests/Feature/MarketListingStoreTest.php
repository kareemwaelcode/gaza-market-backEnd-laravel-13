<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Listing;
use App\Models\ListingCategory;
use App\Models\ListingImage;
use App\Models\Regions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MarketListingStoreTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $user = new User();
        $user->user_phone = '059'.random_int(1000000, 9999999);
        $user->user_full_name = 'Test User';
        $user->save();

        return $user;
    }

    private function validData(array $overrides = []): array
    {
        $region = new Regions();
        $region->region_slug = 'r-'.Str::random(6);
        $region->region_name_en = 'Gaza';
        $region->region_name_ar = 'غزة';
        $region->save();

        $area = new Area();
        $area->area_slug = 'a-'.Str::random(6);
        $area->area_name_en = 'Rimal';
        $area->area_name_ar = 'الرمال';
        $area->region_id = $region->id;
        $area->save();

        $category = new ListingCategory();
        $category->listing_category_slug = 'c-'.Str::random(6);
        $category->listing_category_name_en = 'Phones';
        $category->listing_category_name_ar = 'جوالات';
        $category->save();

        return array_merge([
            'listing_title' => 'Test item',
            'listing_condition' => 'used',
            'listing_description' => 'Description',
            'listing_price' => 150,
            'listing_is_negotiable' => '1',
            'listing_phone' => '0591234567',
            'listing_category_id' => $category->id,
            'area_id' => $area->id,
            'images' => [UploadedFile::fake()->image('a.jpg')],
        ], $overrides);
    }

    private function images(int $count): array
    {
        return array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, $count));
    }

    private function submit(User $user, array $data)
    {
        return $this->actingAs($user)->post('/api/market', $data, ['Accept' => 'application/json']);
    }

    public function test_guest_cannot_create_a_listing(): void
    {
        Storage::fake('public');

        $this->post('/api/market', $this->validData(), ['Accept' => 'application/json'])->assertUnauthorized();

        $this->assertDatabaseCount('listings', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_one_image_is_stored_and_its_path_is_saved(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $response = $this->submit($user, $this->validData());

        $response->assertCreated()->assertJsonPath('data.status', 'pending');

        $listing = Listing::with('images')->firstOrFail();
        $this->assertSame($user->id, $listing->user_id);
        $this->assertCount(1, $listing->images);

        $image = $listing->images->first();
        $this->assertSame(1, $image->listing_image_sort_order);
        $this->assertStringStartsWith("listings/{$listing->id}/", $image->listing_image_path);
        $this->assertStringNotContainsString('https://', $image->listing_image_path);
        Storage::disk('public')->assertExists($image->listing_image_path);
    }

    public function test_three_images_are_stored_in_the_order_they_were_sent(): void
    {
        Storage::fake('public');

        $this->submit($this->makeUser(), $this->validData(['images' => $this->images(3)]))->assertCreated();

        $images = Listing::firstOrFail()->images;

        $this->assertSame([1, 2, 3], $images->pluck('listing_image_sort_order')->all());
        $this->assertCount(3, array_unique($images->pluck('listing_image_path')->all()));

        foreach ($images as $image) {
            Storage::disk('public')->assertExists($image->listing_image_path);
        }
    }

    public function test_the_response_returns_one_url_per_image(): void
    {
        Storage::fake('public');

        $response = $this->submit($this->makeUser(), $this->validData(['images' => $this->images(2)]));

        $response->assertCreated();
        $this->assertCount(2, $response->json('data.images'));
    }

    public function test_owner_status_and_publish_date_cannot_be_set_by_the_client(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $other = $this->makeUser();

        $this->submit($user, $this->validData([
            'user_id' => $other->id,
            'listing_status' => 'approved',
            'listing_published_at' => now()->toDateTimeString(),
        ]))->assertCreated();

        $listing = Listing::firstOrFail();
        $this->assertSame($user->id, $listing->user_id);
        $this->assertSame('pending', $listing->listing_status);
        $this->assertNull($listing->listing_published_at);
    }

    public function test_negotiable_and_whatsapp_are_optional(): void
    {
        Storage::fake('public');
        $data = $this->validData();
        unset($data['listing_is_negotiable']);

        $this->submit($this->makeUser(), $data)->assertCreated();

        $listing = Listing::firstOrFail();
        $this->assertFalse($listing->listing_is_negotiable);
        $this->assertNull($listing->listing_whatsapp);
    }

    public function test_international_whatsapp_is_saved_and_builds_a_wa_me_link(): void
    {
        Storage::fake('public');

        $this->submit($this->makeUser(), $this->validData(['listing_whatsapp' => '+970561234567']))->assertCreated();

        $listing = Listing::firstOrFail();
        $this->assertSame('+970561234567', $listing->listing_whatsapp);
        $this->assertSame('https://wa.me/970561234567', $listing->whatsapp_link);
    }

    public function test_local_format_whatsapp_is_rejected(): void
    {
        Storage::fake('public');

        $this->submit($this->makeUser(), $this->validData(['listing_whatsapp' => '0561234567']))->assertStatus(400);

        $this->assertDatabaseCount('listings', 0);
    }

    public function test_invalid_image_sets_are_rejected_without_touching_the_database_or_disk(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $cases = [
            'no images' => [],
            'four images' => $this->images(4),
            'not an image' => [UploadedFile::fake()->create('doc.pdf', 10, 'application/pdf')],
            'too large' => [UploadedFile::fake()->image('big.jpg')->size(2049)],
        ];

        foreach ($cases as $label => $images) {
            $this->submit($user, $this->validData(['images' => $images]))->assertStatus(400);

            $this->assertDatabaseCount('listings', 0);
            $this->assertDatabaseCount('listing_images', 0);
            $this->assertSame([], Storage::disk('public')->allFiles(), $label);
        }
    }

    public function test_a_failure_while_saving_images_rolls_back_the_listing_and_removes_the_files(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $data = $this->validData(['images' => $this->images(3)]);

        $calls = 0;
        ListingImage::creating(function () use (&$calls) {
            if (++$calls === 2) {
                throw new RuntimeException('boom');
            }
        });

        $this->submit($user, $data)
            ->assertStatus(500)
            ->assertJsonPath('icon', 'error')
            ->assertJsonMissingPath('exception');

        $this->assertDatabaseCount('listings', 0);
        $this->assertDatabaseCount('listing_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

        public function test_double_submit_creates_one_listing_and_stores_images_once(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $data = $this->validData();

        $first = $this->submit($user, array_merge($data, ['images' => $this->images(2)]));
        $second = $this->submit($user, array_merge($data, ['images' => $this->images(2)]));

        $first->assertCreated();
        $second->assertOk()
            ->assertJsonPath('data.id', $first->json('data.id'))
            ->assertJsonPath('data.images', $first->json('data.images'));

        $this->assertDatabaseCount('listings', 1);
        $this->assertDatabaseCount('listing_images', 2);
        $this->assertCount(2, Storage::disk('public')->allFiles());
    }

    public function test_a_different_listing_from_the_same_user_is_created_normally(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $data = $this->validData();

        $this->submit($user, array_merge($data, ['images' => $this->images(1)]))->assertCreated();
        $this->submit($user, array_merge($data, ['listing_title' => 'Another item', 'images' => $this->images(1)]))->assertCreated();

        $this->assertDatabaseCount('listings', 2);
    }

    public function test_the_same_data_from_another_user_is_created_normally(): void
    {
        Storage::fake('public');
        $data = $this->validData();

        $this->submit($this->makeUser(), array_merge($data, ['images' => $this->images(1)]))->assertCreated();
        $this->submit($this->makeUser(), array_merge($data, ['images' => $this->images(1)]))->assertCreated();

        $this->assertDatabaseCount('listings', 2);
    }

    public function test_the_same_data_after_the_duplicate_window_is_created_normally(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $data = $this->validData();

        $this->submit($user, array_merge($data, ['images' => $this->images(1)]))->assertCreated();

        $this->travel(61)->seconds();

        $this->submit($user, array_merge($data, ['images' => $this->images(1)]))->assertCreated();

        $this->assertDatabaseCount('listings', 2);
    }

    public function test_a_request_is_rejected_while_the_users_lock_is_held(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();

        $lock = Cache::lock("listing-store:{$user->id}", 15);
        $this->assertTrue($lock->get());

        $this->submit($user, $this->validData())
            ->assertStatus(409)
            ->assertJsonPath('icon', 'error');

        $this->assertDatabaseCount('listings', 0);
        $this->assertDatabaseCount('listing_images', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());

        $lock->release();
    }

    public function test_the_lock_is_released_after_a_failed_request_so_a_retry_succeeds(): void
    {
        Storage::fake('public');
        $user = $this->makeUser();
        $data = $this->validData();

        $calls = 0;
        ListingImage::creating(function () use (&$calls) {
            if (++$calls === 1) {
                throw new RuntimeException('boom');
            }
        });

        $this->submit($user, array_merge($data, ['images' => $this->images(1)]))->assertStatus(500);
        $this->assertDatabaseCount('listings', 0);

        $this->submit($user, array_merge($data, ['images' => $this->images(1)]))->assertCreated();
        $this->assertDatabaseCount('listings', 1);
    }
}