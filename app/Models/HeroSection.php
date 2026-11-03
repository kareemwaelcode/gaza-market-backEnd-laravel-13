<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeroSection extends Model
{
    protected $table = 'hero_sections';

    protected $fillable = [
        'hero_section_eyebrow_ar',
        'hero_section_eyebrow_en',
        'hero_section_title_ar',
        'hero_section_title_en',
        'hero_section_subtitle_ar',
        'hero_section_subtitle_en',
        'hero_section_location_ar',
        'hero_section_location_en',
        'hero_section_products_label_ar',
        'hero_section_products_label_en',
        'hero_section_prices_label_ar',
        'hero_section_prices_label_en',
        'hero_section_listings_label_ar',
        'hero_section_listings_label_en',
        'hero_section_places_label_ar',
        'hero_section_places_label_en',
        'hero_section_ticker_label_ar',
        'hero_section_ticker_label_en',
    ];
}