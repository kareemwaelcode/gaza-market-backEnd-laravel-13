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
    Schema::create('category_groups', function (Blueprint $table) {
        $table->id();
        $table->string("category_group_slug", 45)->unique();
        $table->string("category_group_name_en", 45);
        $table->string("category_group_name_ar", 45);
        $table->tinyInteger("category_group_sort_order")->nullable();
        $table->timestamps();
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('category_groups');
    }
};
