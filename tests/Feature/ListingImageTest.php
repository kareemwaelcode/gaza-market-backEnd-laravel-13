<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Listing;
use App\Models\ListingCategory;
use App\Models\Regions;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListingImageTest extends TestCase
{
    use RefreshDatabase;

    private function listing(): Listing
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

        $user = new User();
        $user->user_phone = '059'.random_int(1000000, 9999999);
        $user->user_full_name = 'Test User';
        $user->save();

        $category = new ListingCategory();
        $category->listing_category_slug = 'c-'.Str::random(6);
        $category->listing_category_name_en = 'Phones';
        $category->listing_category_name_ar = 'جوالات';
        $category->save();

        $listing = new Listing();
        $listing->fill([
            'listing_title' => 'Test item',
            'listing_condition' => 'used',
            'listing_description' => 'Description',
            'listing_price' => 150,
            'listing_phone' => '0591234567',
            'listing_category_id' => $category->id,
            'area_id' => $area->id,
        ]);
        $listing->user_id = $user->id;
        $listing->save();

        return $listing->refresh();
    }

    public function test_images_come_back_in_sort_order(): void
    {
        $listing = $this->listing();
        $listing->images()->create(['listing_image_path' => 'listings/c.jpg', 'listing_image_sort_order' => 3]);
        $listing->images()->create(['listing_image_path' => 'listings/a.jpg', 'listing_image_sort_order' => 1]);
        $listing->images()->create(['listing_image_path' => 'listings/b.jpg', 'listing_image_sort_order' => 2]);

        $this->assertSame(
            ['listings/a.jpg', 'listings/b.jpg', 'listings/c.jpg'],
            $listing->load('images')->images->pluck('listing_image_path')->all()
        );
    }

    public function test_same_sort_order_twice_for_one_listing_is_rejected(): void
    {
        $listing = $this->listing();
        $listing->images()->create(['listing_image_path' => 'listings/a.jpg', 'listing_image_sort_order' => 1]);

        $this->expectException(QueryException::class);

        $listing->images()->create(['listing_image_path' => 'listings/b.jpg', 'listing_image_sort_order' => 1]);
    }

    public function test_url_accessor_handles_local_and_external_paths(): void
    {
        Storage::fake('public');
        $listing = $this->listing();

        $local = $listing->images()->create(['listing_image_path' => 'listings/a.jpg', 'listing_image_sort_order' => 1]);
        $external = $listing->images()->create(['listing_image_path' => 'https://example.com/x.jpg', 'listing_image_sort_order' => 2]);

        $this->assertTrue($local->isLocalFile());
        $this->assertStringEndsWith('listings/a.jpg', $local->url);
        $this->assertFalse($external->isLocalFile());
        $this->assertSame('https://example.com/x.jpg', $external->url);
    }

    public function test_show_page_renders_the_slider_when_there_are_images(): void
    {
        $listing = $this->listing();
        $listing->images()->create(['listing_image_path' => 'https://example.com/a.jpg', 'listing_image_sort_order' => 1]);
        $listing->images()->create(['listing_image_path' => 'https://example.com/b.jpg', 'listing_image_sort_order' => 2]);

        $this->get("/cms/admins/listing/{$listing->id}")
            ->assertOk()
            ->assertSee('listingPhotos')
            ->assertSee('https://example.com/a.jpg', false)
            ->assertSee('(2/3)');
    }

    public function test_show_page_shows_empty_state_without_images(): void
    {
        $listing = $this->listing();

        $this->get("/cms/admins/listing/{$listing->id}")
            ->assertOk()
            ->assertSee('No photos')
            ->assertDontSee('listingPhotos');
    }

    public function test_deleting_a_listing_removes_its_image_rows_and_local_files(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('listings/a.jpg', 'fake');

        $listing = $this->listing();
        $listing->images()->create(['listing_image_path' => 'listings/a.jpg', 'listing_image_sort_order' => 1]);
        $listing->images()->create(['listing_image_path' => 'https://example.com/x.jpg', 'listing_image_sort_order' => 2]);

        $this->deleteJson("/cms/admins/listing/{$listing->id}")->assertOk();

        $this->assertDatabaseMissing('listing_images', ['listing_id' => $listing->id]);
        Storage::disk('public')->assertMissing('listings/a.jpg');
    }

    public function test_deleting_a_listing_removes_its_whole_image_folder(): void
    {
        Storage::fake('public');

        $listing = $this->listing();
        $dir = "listings/{$listing->id}";

        Storage::disk('public')->put("{$dir}/a.jpg", 'fake');
        // ملف متبقي بالمجلد ما إله صف بقاعدة البيانات
        Storage::disk('public')->put("{$dir}/leftover.jpg", 'fake');
        $listing->images()->create(['listing_image_path' => "{$dir}/a.jpg", 'listing_image_sort_order' => 1]);

        $this->deleteJson("/cms/admins/listing/{$listing->id}")->assertOk();

        Storage::disk('public')->assertMissing("{$dir}/a.jpg");
        Storage::disk('public')->assertMissing("{$dir}/leftover.jpg");
        $this->assertFalse(is_dir(Storage::disk('public')->path($dir)));
    }
}