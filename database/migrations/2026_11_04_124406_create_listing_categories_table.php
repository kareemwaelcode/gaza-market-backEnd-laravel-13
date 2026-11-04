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
Schema::create('listing_categories', function (Blueprint $table) {
    $table->id();
    // معرّف ثابت يتولد من الاسم الإنجليزي عند الإنشاء ولا يتغير بعدها
    $table->string('listing_category_slug', 45)->unique();
    $table->string('listing_category_name_en', 100);
    $table->string('listing_category_name_ar', 100);
    // false = لا يظهر عند إضافة إعلان جديد
    $table->boolean('listing_category_is_active')->default(true);
    $table->smallInteger('listing_category_sort_order')->nullable();
    $table->timestamps();
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_categories');
    }
};
