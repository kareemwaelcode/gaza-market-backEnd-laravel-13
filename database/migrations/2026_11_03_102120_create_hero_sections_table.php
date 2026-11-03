<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('hero_sections', function (Blueprint $table) {
        $table->id();
        $table->string('hero_section_eyebrow_ar', 100);
        $table->string('hero_section_eyebrow_en', 100);
        $table->string('hero_section_title_ar', 150);
        $table->string('hero_section_title_en', 150);
        $table->string('hero_section_subtitle_ar', 255);
        $table->string('hero_section_subtitle_en', 255);
        $table->string('hero_section_location_ar', 100);
        $table->string('hero_section_location_en', 100);
        $table->string('hero_section_products_label_ar', 50);
        $table->string('hero_section_products_label_en', 50);
        $table->string('hero_section_prices_label_ar', 50);
        $table->string('hero_section_prices_label_en', 50);
        $table->string('hero_section_listings_label_ar', 50);
        $table->string('hero_section_listings_label_en', 50);
        $table->string('hero_section_places_label_ar', 50);
        $table->string('hero_section_places_label_en', 50);
        $table->string('hero_section_ticker_label_ar', 100);
        $table->string('hero_section_ticker_label_en', 100);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('hero_sections');
    }
};
