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
        Schema::create('store_categories', function (Blueprint $table) {
            $table->id();
            $table->string("store_category_slug", 45)->unique();
            $table->string("store_category_variant_key", 45);
            $table->string("store_category_name_en", 45);
            $table->string("store_category_name_ar", 45);
            $table->boolean("store_category_is_active")->default(true);
            $table->smallInteger("store_category_sort_order")->nullable();
            $table->foreignId("store_category_group_id")->constrained("store_category_groups")->restrictOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_categories');
    }
};
