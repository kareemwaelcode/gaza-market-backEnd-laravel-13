<?php

namespace Tests\Feature;

use App\Models\Area;
use App\Models\Listing;
use App\Models\ListingCategory;
use App\Models\Regions;
use App\Models\SavedListing;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class SavedListingTest extends TestCase
{
    use RefreshDatabase;

    private static int $phoneCounter = 0;

    private function makeUser(): User
    {
        $user = new User();
        $user->user_phone = '059'.str_pad((string) ++self::$phoneCounter, 7, '0', STR_PAD_LEFT);
        $user->user_full_name = 'Test User';
        $user->save();

        return $user;
    }

    private function makeListing(string $status = Listing::STATUS_APPROVED, ?User $owner = null): Listing
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

        $owner ??= $this->makeUser();

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
        $listing->user_id = $owner->id;
        $listing->listing_status = $status;
        $listing->listing_published_at = $status === Listing::STATUS_APPROVED ? now() : null;
        $listing->save();

        return $listing->refresh();
    }

    // حفظ مباشر في الجدول (بدون API) مع إمكانية تحديد وقت الحفظ
    private function saveDirect(User $user, Listing $listing, $savedAt = null): SavedListing
    {
        $saved = new SavedListing();
        $saved->user_id = $user->id;
        $saved->listing_id = $listing->id;

        if ($savedAt !== null) {
            $saved->saved_listing_created_at = $savedAt;
        }

        $saved->save();

        return $saved;
    }

    /* ---------------- Auth ---------------- */

    public function test_guest_cannot_save_unsave_or_list(): void
    {
        $listing = $this->makeListing();

        $this->postJson("/api/market/{$listing->id}/save")->assertUnauthorized();
        $this->deleteJson("/api/market/{$listing->id}/save")->assertUnauthorized();
        $this->getJson('/api/market/saved')->assertUnauthorized();

        $this->assertDatabaseCount('saved_listings', 0);
    }

    /* ---------------- Save ---------------- */

    public function test_user_can_save_an_approved_listing(): void
    {
        $user = $this->makeUser();
        $listing = $this->makeListing();

        $this->actingAs($user)
            ->postJson("/api/market/{$listing->id}/save")
            ->assertCreated()
            ->assertJsonPath('data.listing_id', $listing->id)
            ->assertJsonPath('data.saved', true);

        $this->assertDatabaseHas('saved_listings', ['user_id' => $user->id, 'listing_id' => $listing->id]);
        $this->assertNotNull(SavedListing::first()->saved_listing_created_at);
    }

    public function test_saving_twice_keeps_one_row_and_returns_ok(): void
    {
        $user = $this->makeUser();
        $listing = $this->makeListing();

        $this->actingAs($user)->postJson("/api/market/{$listing->id}/save")->assertCreated();
        $this->actingAs($user)->postJson("/api/market/{$listing->id}/save")
            ->assertOk()
            ->assertJsonPath('data.saved', true);

        $this->assertDatabaseCount('saved_listings', 1);
    }

    public function test_two_users_can_save_the_same_listing(): void
    {
        $listing = $this->makeListing();

        $this->actingAs($this->makeUser())->postJson("/api/market/{$listing->id}/save")->assertCreated();
        $this->actingAs($this->makeUser())->postJson("/api/market/{$listing->id}/save")->assertCreated();

        $this->assertDatabaseCount('saved_listings', 2);
    }

    public function test_user_can_save_their_own_listing(): void
    {
        $owner = $this->makeUser();
        $listing = $this->makeListing(Listing::STATUS_APPROVED, $owner);

        $this->actingAs($owner)->postJson("/api/market/{$listing->id}/save")->assertCreated();

        $this->assertDatabaseHas('saved_listings', ['user_id' => $owner->id, 'listing_id' => $listing->id]);
    }

    public function test_only_approved_listings_can_be_saved(): void
    {
        $user = $this->makeUser();

        foreach ([Listing::STATUS_PENDING, Listing::STATUS_REJECTED, Listing::STATUS_SOLD] as $status) {
            $listing = $this->makeListing($status);

            $this->actingAs($user)
                ->postJson("/api/market/{$listing->id}/save")
                ->assertNotFound()
                ->assertJsonPath('title', 'Listing not found');
        }

        $this->assertDatabaseCount('saved_listings', 0);
    }

    public function test_unknown_or_invalid_listing_id_returns_404(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson('/api/market/999999/save')->assertNotFound();
        $this->actingAs($user)->postJson('/api/market/abc/save')->assertNotFound();
        // رقم أكبر من حدود المسار: 404 بدل خطأ 500
        $this->actingAs($user)->postJson('/api/market/99999999999999999999/save')->assertNotFound();

        $this->assertDatabaseCount('saved_listings', 0);
    }

    /* ---------------- Unsave ---------------- */

    public function test_user_can_unsave_a_listing(): void
    {
        $user = $this->makeUser();
        $listing = $this->makeListing();
        $this->saveDirect($user, $listing);

        $this->actingAs($user)
            ->deleteJson("/api/market/{$listing->id}/save")
            ->assertOk()
            ->assertJsonPath('data.saved', false);

        $this->assertDatabaseCount('saved_listings', 0);
    }

    public function test_unsaving_a_listing_that_is_not_saved_is_not_an_error(): void
    {
        $user = $this->makeUser();
        $listing = $this->makeListing();

        $this->actingAs($user)->deleteJson("/api/market/{$listing->id}/save")->assertOk();
        $this->actingAs($user)->deleteJson('/api/market/999999/save')->assertOk();
    }

    public function test_user_can_unsave_a_listing_that_is_no_longer_approved(): void
    {
        $user = $this->makeUser();
        $listing = $this->makeListing();
        $this->saveDirect($user, $listing);

        $listing->listing_status = Listing::STATUS_SOLD;
        $listing->save();

        $this->actingAs($user)->deleteJson("/api/market/{$listing->id}/save")->assertOk();

        $this->assertDatabaseCount('saved_listings', 0);
    }

    public function test_user_cannot_remove_another_users_saved_listing(): void
    {
        $owner = $this->makeUser();
        $other = $this->makeUser();
        $listing = $this->makeListing();
        $this->saveDirect($owner, $listing);

        $this->actingAs($other)->deleteJson("/api/market/{$listing->id}/save")->assertOk();

        $this->assertDatabaseHas('saved_listings', ['user_id' => $owner->id, 'listing_id' => $listing->id]);
    }

    /* ---------------- List ---------------- */

    public function test_index_returns_only_my_saved_listings_newest_saved_first(): void
    {
        $me = $this->makeUser();
        $other = $this->makeUser();
        $old = $this->makeListing();
        $new = $this->makeListing();
        $notMine = $this->makeListing();

        $this->saveDirect($me, $old, now()->subMinutes(10));
        $this->saveDirect($me, $new, now()->subMinute());
        $this->saveDirect($other, $notMine);

        $response = $this->actingAs($me)->getJson('/api/market/saved')->assertOk();

        $this->assertSame([$new->id, $old->id], collect($response->json('data'))->pluck('id')->all());
        $response->assertJsonPath('meta.total', 2);
    }

    public function test_index_hides_listings_that_are_not_approved_and_shows_them_again_when_approved(): void
    {
        $me = $this->makeUser();
        $listing = $this->makeListing();
        $this->saveDirect($me, $listing);

        foreach ([Listing::STATUS_SOLD, Listing::STATUS_REJECTED, Listing::STATUS_PENDING] as $status) {
            $listing->listing_status = $status;
            $listing->save();

            $this->actingAs($me)->getJson('/api/market/saved')->assertOk()->assertJsonPath('meta.total', 0);
            // صف الحفظ ما انحذف
            $this->assertDatabaseCount('saved_listings', 1);
        }

        $listing->listing_status = Listing::STATUS_APPROVED;
        $listing->save();

        $this->actingAs($me)->getJson('/api/market/saved')->assertOk()->assertJsonPath('meta.total', 1);
    }

    public function test_index_returns_card_fields_and_no_private_data(): void
    {
        $me = $this->makeUser();
        $listing = $this->makeListing();
        $listing->images()->create(['listing_image_path' => 'listings/b.jpg', 'listing_image_sort_order' => 2]);
        $listing->images()->create(['listing_image_path' => 'listings/a.jpg', 'listing_image_sort_order' => 1]);
        $this->saveDirect($me, $listing);

        $response = $this->actingAs($me)->getJson('/api/market/saved')->assertOk();

        $response->assertJsonPath('data.0.id', $listing->id)
            ->assertJsonPath('data.0.title', 'Test item')
            ->assertJsonPath('data.0.price', '150.00')
            ->assertJsonPath('data.0.area.name_ar', 'الرمال')
            ->assertJsonPath('data.0.area.name_en', 'Rimal');

        // الغلاف هو الصورة الأولى بالترتيب
        $this->assertStringEndsWith('listings/a.jpg', $response->json('data.0.cover'));

        // لا رقم جوال ولا بيانات صاحب الإعلان
        $response->assertJsonMissingPath('data.0.phone')
            ->assertJsonMissingPath('data.0.listing_phone')
            ->assertJsonMissingPath('data.0.user_id')
            ->assertJsonMissingPath('data.0.user');
    }

    public function test_index_is_paginated(): void
    {
        $me = $this->makeUser();

        for ($i = 1; $i <= 21; $i++) {
            $this->saveDirect($me, $this->makeListing(), now()->subMinutes(100 - $i));
        }

        $first = $this->actingAs($me)->getJson('/api/market/saved')->assertOk();
        $this->assertCount(20, $first->json('data'));
        $first->assertJsonPath('meta.total', 21)->assertJsonPath('meta.last_page', 2)->assertJsonPath('meta.per_page', 20);

        $second = $this->actingAs($me)->getJson('/api/market/saved?page=2')->assertOk();
        $this->assertCount(1, $second->json('data'));
    }

    public function test_index_query_count_does_not_grow_with_the_number_of_listings(): void
    {
        $me = $this->makeUser();

        for ($i = 0; $i < 2; $i++) {
            $listing = $this->makeListing();
            $listing->images()->create(['listing_image_path' => "listings/{$i}.jpg", 'listing_image_sort_order' => 1]);
            $this->saveDirect($me, $listing);
        }

        DB::enableQueryLog();
        $this->actingAs($me)->getJson('/api/market/saved')->assertOk();
        $small = count(DB::getQueryLog());

        for ($i = 2; $i < 8; $i++) {
            $listing = $this->makeListing();
            $listing->images()->create(['listing_image_path' => "listings/{$i}.jpg", 'listing_image_sort_order' => 1]);
            $this->saveDirect($me, $listing);
        }

        DB::flushQueryLog();
        $this->actingAs($me)->getJson('/api/market/saved')->assertOk();
        $large = count(DB::getQueryLog());

        $this->assertSame($small, $large);
    }

    /* ---------------- Database ---------------- */

    public function test_the_same_listing_cannot_be_saved_twice_by_the_same_user_at_database_level(): void
    {
        $user = $this->makeUser();
        $listing = $this->makeListing();
        $this->saveDirect($user, $listing);

        $this->expectException(QueryException::class);

        $this->saveDirect($user, $listing);
    }

    public function test_deleting_a_listing_removes_its_saved_rows(): void
    {
        $listing = $this->makeListing();
        $this->saveDirect($this->makeUser(), $listing);
        $this->saveDirect($this->makeUser(), $listing);

        $listing->delete();

        $this->assertDatabaseCount('saved_listings', 0);
    }

    public function test_force_deleting_a_user_removes_their_saved_rows_but_keeps_the_listing(): void
    {
        $user = $this->makeUser();
        $listing = $this->makeListing();
        $this->saveDirect($user, $listing);

        $user->forceDelete();

        $this->assertDatabaseCount('saved_listings', 0);
        $this->assertDatabaseHas('listings', ['id' => $listing->id]);
    }

    public function test_relations_between_user_listing_and_saved_listing(): void
    {
        $user = $this->makeUser();
        $listing = $this->makeListing();
        $saved = $this->saveDirect($user, $listing);

        $this->assertTrue($user->savedListings->contains($saved));
        $this->assertTrue($listing->saves->contains($saved));
        $this->assertSame($listing->id, $saved->listing->id);
        $this->assertSame($user->id, $saved->user->id);
    }

    /* ---------------- Rate limit ---------------- */

    public function test_requests_are_rate_limited_per_user(): void
    {
        $user = $this->makeUser();
        $other = $this->makeUser();

        for ($i = 0; $i < 60; $i++) {
            $this->actingAs($user)->deleteJson('/api/market/1/save')->assertOk();
        }

        $this->actingAs($user)->deleteJson('/api/market/1/save')->assertStatus(429);

        // مستخدم ثاني ما بيتأثر
        $this->actingAs($other)->deleteJson('/api/market/1/save')->assertOk();
    }
}