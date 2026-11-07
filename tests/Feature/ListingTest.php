<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Listing;
use App\Models\ListingCategory;
use App\Models\Regions;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class ListingTest extends TestCase
{
    use RefreshDatabase;

    private function listing(string $status = 'pending', string $title = 'Test item'): Listing
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
            'listing_title' => $title,
            'listing_condition' => 'used',
            'listing_description' => 'Description',
            'listing_price' => 150,
            'listing_phone' => '0591234567',
            'listing_category_id' => $category->id,
            'area_id' => $area->id,
        ]);
        $listing->user_id = $user->id;
        $listing->listing_status = $status;
        if ($status === 'approved') {
            $listing->listing_published_at = now();
        }
        $listing->save();

        return $listing->refresh();
    }

    public function test_new_listing_defaults(): void
    {
        $listing = $this->listing('pending');

        $this->assertSame('pending', $listing->listing_status);
        $this->assertNull($listing->listing_published_at);
        $this->assertFalse($listing->listing_is_negotiable);
        $this->assertNull($listing->listing_whatsapp);
    }

    public function test_whatsapp_link_is_null_without_a_whatsapp_number(): void
    {
        $this->assertNull($this->listing('pending')->whatsapp_link);
    }

    public function test_status_user_and_published_at_are_not_mass_assignable(): void
    {
        $listing = (new Listing())->fill([
            'listing_title' => 'x',
            'listing_status' => 'approved',
            'listing_published_at' => now(),
            'user_id' => 99,
        ]);

        $this->assertNull($listing->listing_status);
        $this->assertNull($listing->listing_published_at);
        $this->assertNull($listing->user_id);
    }

    /* ---------------- CMS ---------------- */

    public function test_index_filters_by_status(): void
    {
        $this->listing('approved', 'Approved item');
        $this->listing('pending', 'Pending item');

        $this->get('/cms/admins/listing?status=approved')
            ->assertOk()
            ->assertSee('Approved item')
            ->assertDontSee('Pending item');
    }

    public function test_index_rejects_unknown_status_filter(): void
    {
        $this->getJson('/cms/admins/listing?status=hacked')->assertStatus(422);
    }

    public function test_index_ignores_an_invalid_filter_and_shows_a_message_in_the_browser(): void
    {
        $this->listing('approved', 'Approved item');
        $this->listing('pending', 'Pending item');

        $this->get('/cms/admins/listing?status=abc')
            ->assertOk()
            ->assertSee('Invalid filter value ignored')
            ->assertSee('Approved item')
            ->assertSee('Pending item');
    }

    public function test_index_keeps_valid_filters_when_another_one_is_invalid(): void
    {
        $this->listing('approved', 'Approved item');
        $this->listing('pending', 'Pending item');

        $this->get('/cms/admins/listing?status=approved&area=abc')
            ->assertOk()
            ->assertSee('Invalid filter value ignored (area)')
            ->assertSee('Approved item')
            ->assertDontSee('Pending item');
    }

    public function test_index_shows_no_warning_when_filters_are_valid(): void
    {
        $this->listing('approved', 'Approved item');

        $this->get('/cms/admins/listing?status=approved')
            ->assertOk()
            ->assertDontSee('Invalid filter value ignored');
    }

    public function test_approve_publishes_a_pending_listing(): void
    {
        $listing = $this->listing('pending');

        $this->postJson("/cms/admins/listing_approve/{$listing->id}")->assertOk();

        $listing->refresh();
        $this->assertSame('approved', $listing->listing_status);
        $this->assertNotNull($listing->listing_published_at);
        $this->assertNull($listing->listing_rejection_reason);
    }

    public function test_approve_is_refused_for_sold_and_already_approved_listings(): void
    {
        $sold = $this->listing('sold');
        $approved = $this->listing('approved');

        $this->postJson("/cms/admins/listing_approve/{$sold->id}")->assertStatus(409);
        $this->postJson("/cms/admins/listing_approve/{$approved->id}")->assertStatus(409);

        $this->assertSame('sold', $sold->refresh()->listing_status);
    }

    public function test_reject_requires_a_reason(): void
    {
        $listing = $this->listing('pending');

        $this->postJson("/cms/admins/listing_reject/{$listing->id}", ['listing_rejection_reason' => ''])
            ->assertStatus(400)
            ->assertJson(['icon' => 'error']);

        $this->assertSame('pending', $listing->refresh()->listing_status);
    }

    public function test_reject_stores_reason_and_clears_publish_date(): void
    {
        $listing = $this->listing('pending');
        $this->postJson("/cms/admins/listing_approve/{$listing->id}")->assertOk();

        $this->postJson("/cms/admins/listing_reject/{$listing->id}", ['listing_rejection_reason' => 'Blurry photos'])
            ->assertOk();

        $listing->refresh();
        $this->assertSame('rejected', $listing->listing_status);
        $this->assertSame('Blurry photos', $listing->listing_rejection_reason);
        $this->assertNull($listing->listing_published_at);
    }

    public function test_sold_listing_cannot_be_rejected(): void
    {
        $listing = $this->listing('sold');

        $this->postJson("/cms/admins/listing_reject/{$listing->id}", ['listing_rejection_reason' => 'x'])
            ->assertStatus(409);

        $this->assertSame('sold', $listing->refresh()->listing_status);
    }

    public function test_destroy_deletes_the_listing(): void
    {
        $listing = $this->listing('approved');

        $this->deleteJson("/cms/admins/listing/{$listing->id}")->assertOk();

        $this->assertDatabaseMissing('listings', ['id' => $listing->id]);
    }
}