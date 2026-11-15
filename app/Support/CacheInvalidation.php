<?php

namespace App\Support;

use App\Models\Area;
use App\Models\Category;
use App\Models\CategoryGroup;
use App\Models\Listing;
use App\Models\ListingCategory;
use App\Models\Place;
use App\Models\Regions;
use App\Models\StoreCategory;
use App\Models\StoreCategoryGroup;
use App\Models\Unit;

class CacheInvalidation
{
    public static function register(): void
    {
        $lookups = [
            Regions::class,
            Area::class,
            Unit::class,
            Category::class,
            CategoryGroup::class,
            StoreCategory::class,
            StoreCategoryGroup::class,
            ListingCategory::class,
        ];

        foreach ($lookups as $model) {
            $model::saved(fn () => ApiCache::lookupsChanged());
            $model::deleted(fn () => ApiCache::lookupsChanged());
        }

        Listing::saved(function (Listing $listing) {
            if ($listing->listing_status === Listing::STATUS_APPROVED || $listing->wasChanged('listing_status')) {
                ApiCache::marketChanged();
            }
        });

        Listing::deleted(function (Listing $listing) {
            if ($listing->listing_status === Listing::STATUS_APPROVED) {
                ApiCache::marketChanged();
            }
        });

        Place::saved(function (Place $place) {
            if ($place->place_status === Place::STATUS_APPROVED || $place->wasChanged('place_status')) {
                ApiCache::placesChanged();
            }
        });

        Place::deleted(function (Place $place) {
            if ($place->place_status === Place::STATUS_APPROVED) {
                ApiCache::placesChanged();
            }
        });
    }
}