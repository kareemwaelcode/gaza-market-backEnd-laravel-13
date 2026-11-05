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
Schema::create('listing_images', function (Blueprint $table) {
    $table->id();
    // الصورة بتنحذف مع إعلانها
    $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
    // مسار نسبي على disk الـ public (أو رابط https كامل للبيانات التجريبية فقط)
    $table->string('listing_image_path', 255);
    // ترتيب الصورة (1 إلى 3)، والأولى هي الغلاف
    $table->unsignedTinyInteger('listing_image_sort_order');
    // ما بتتكرر نفس الرتبة بنفس الإعلان
    $table->unique(['listing_id', 'listing_image_sort_order'], 'listing_images_listing_order_unique');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listing_images');
    }
};
