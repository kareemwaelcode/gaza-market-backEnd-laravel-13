<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->index(['listing_status', 'area_id', 'listing_published_at'], 'listings_status_area_published_idx');
            $table->index(['listing_status', 'listing_category_id', 'listing_published_at'], 'listings_status_category_published_idx');
        });
    }

    public function down(): void
    {
        Schema::table('listings', function (Blueprint $table) {
            $table->dropIndex('listings_status_area_published_idx');
            $table->dropIndex('listings_status_category_published_idx');
        });
    }
};