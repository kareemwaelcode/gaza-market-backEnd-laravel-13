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
        Schema::create('navigation_items', function (Blueprint $table) {
        $table->id();
        $table->string('navigation_item_slug', 45)->unique();
        $table->string('navigation_item_name_ar', 45);
        $table->string('navigation_item_name_en', 45);
        $table->string('navigation_item_icon', 45);
        $table->string('navigation_item_url', 255)->nullable();
        $table->unsignedTinyInteger('navigation_item_sort_order')->nullable();
        $table->boolean('navigation_item_is_active')->default(true);
        $table->boolean('navigation_item_is_mobile')->default(true);
        $table->boolean('navigation_item_is_desktop')->default(true);
        $table->boolean('navigation_item_is_coming_soon')->default(false);
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('navigation_items');
    }
};
