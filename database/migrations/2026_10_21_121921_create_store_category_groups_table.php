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
        Schema::create('store_category_groups', function (Blueprint $table) {
        $table->id();
        $table->string("store_category_group_slug", 45)->unique();
        $table->string("store_category_group_name_en", 45);
        $table->string("store_category_group_name_ar", 45);
        $table->tinyInteger("store_category_group_sort_order")->default(0);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_category_groups');
    }
};
