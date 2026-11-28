<?php

namespace App\Support;

use App\Models\HeroSection;
use App\Models\Listing;
use App\Models\Place;
use App\Models\Product;
use App\Models\ProductPrice;

class HeroData
{
    /**
     * نصوص وأرقام الهيرو، مصدر واحد للصفحة الرئيسية ولـ GET /api/hero.
     */
    public static function get(): array
    {
        // النصوص والأرقام بتتخزن مع بعض في الكاش، وبتتجدد لما أي مجموعة منهم تتغير.
        return ApiCache::remember(
            [ApiCache::LOOKUPS, ApiCache::PRICES, ApiCache::PRODUCTS, ApiCache::MARKET, ApiCache::PLACES],
            'hero',
            (int) config('prices.cache.feed_ttl'),
            function () {
                $section = HeroSection::orderBy('id')->first();

                return [
                    // الحقول المسموحة بس (من $fillable)، من غير id ولا timestamps.
                    'section' => $section?->only($section->getFillable()),
                    'stats' => [
                        'products' => Product::active()->count(),
                        'prices' => ProductPrice::approved()
                            ->whereIn('product_id', Product::active()->select('id'))
                            ->count(),
                        'listings' => Listing::approved()->count(),
                        'places' => Place::approved()->count(),
                    ],
                ];
            },
            fn (array $value) => $value['section'] === null,
        );
    }
}