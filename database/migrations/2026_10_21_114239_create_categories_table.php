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
        Schema::create('categories', function (Blueprint $table) {
        $table->id();
        $table->string("category_slug", 45)->unique();
        $table->string("category_name_en", 45);
        $table->string("category_name_ar", 45);
        $table->boolean("category_is_active")->default(true);
        $table->smallInteger("category_sort_order")->nullable();
        $table->foreignId("category_group_id")->constrained("category_groups")->restrictOnDelete();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('categories');
    }
};
