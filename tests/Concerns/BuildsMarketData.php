<?php

namespace Tests\Concerns;

use App\Models\Area;
use App\Models\Listing;
use App\Models\ListingCategory;
use App\Models\Place;
use App\Models\Regions;
use App\Models\User;
use Illuminate\Support\Str;

trait BuildsMarketData
{
    private static int $marketCounter = 0;

    protected function makeUser(string $name = 'Test User'): User
    {
        $user = new User();
        $user->user_phone = '059'.str_pad((string) ++self::$marketCounter, 7, '0', STR_PAD_LEFT);
        $user->user_full_name = $name;
        $user->save();

        return $user;
    }

    protected function makeArea(): Area
    {
        $region = new Regions();
        $region->region_slug = 'r-'.Str::lower(Str::random(6));
        $region->region_name_en = 'Gaza';
        $region->region_name_ar = 'غزة';
        $region->save();

        $area = new Area();
        $area->area_slug = 'a-'.Str::lower(Str::random(6));
        $area->area_name_en = 'Rimal';
        $area->area_name_ar = 'الرمال';
        $area->region_id = $region->id;
        $area->save();

        return $area;
    }

    protected function makeListingCategory(bool $active = true): ListingCategory
    {
        $category = new ListingCategory();
        $category->listing_category_slug = 'c-'.Str::lower(Str::random(6));
        $category->listing_category_name_en = 'Phones';
        $category->listing_category_name_ar = 'جوالات';
        $category->listing_category_is_active = $active;
        $category->save();

        return $category;
    }

    protected function makeListing(User $owner, string $status = Listing::STATUS_APPROVED, array $attrs = []): Listing
    {
        $area = $attrs['area'] ?? $this->makeArea();
        $category = $attrs['category'] ?? $this->makeListingCategory();

        $listing = new Listing();
        $listing->fill([
            'listing_title' => $attrs['title'] ?? 'Test item',
            'listing_condition' => $attrs['condition'] ?? 'used',
            'listing_description' => 'Description',
            'listing_price' => 150,
            'listing_phone' => '0591234567',
            'listing_category_id' => $category->id,
            'area_id' => $area->id,
        ]);
        $listing->user_id = $owner->id;
        $listing->listing_status = $status;
        $listing->listing_published_at = $status === Listing::STATUS_APPROVED
            ? ($attrs['published_at'] ?? now())
            : null;
        $listing->save();

        $listing->images()->create([
            'listing_image_path' => 'https://example.com/'.$listing->id.'.jpg',
            'listing_image_sort_order' => 1,
        ]);

        return $listing->refresh();
    }

    protected function makePlace(User $owner, string $status = Place::STATUS_APPROVED, array $attrs = []): Place
    {
        $area = $attrs['area'] ?? $this->makeArea();
        $counter = ++self::$marketCounter;

        $place = new Place();
        $place->fill([
            'place_name' => $attrs['name'] ?? 'Test place',
            'place_type' => $attrs['type'] ?? 'cafe',
            'place_address' => 'Main street',
            'place_phone' => '056'.str_pad((string) $counter, 7, '0', STR_PAD_LEFT),
            'place_whatsapp' => '+97059'.str_pad((string) $counter, 7, '0', STR_PAD_LEFT),
            'store_category_id' => null,
            'area_id' => $area->id,
        ]);
        $place->user_id = $owner->id;
        $place->place_slug = 'p-'.Str::lower(Str::random(12));
        $place->place_status = $status;
        $place->place_approved_at = $status === Place::STATUS_APPROVED ? ($attrs['approved_at'] ?? now()) : null;
        $place->save();

        return $place->refresh();
    }
}