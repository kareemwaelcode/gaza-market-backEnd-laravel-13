<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_store_services', function (Blueprint $table) {
            $table->id();
            // الخدمة تابعة للمحل: حذف المحل يحذف خدماته (مثل المنتجات والتصنيفات).
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            // التصنيف اختياري: حذفه يترك الخدمة بدون تصنيف.
            $table->foreignId('item_category_id')->nullable()
                ->constrained('dashboard_item_categories')->nullOnDelete();
            $table->string('name', 100);
            $table->string('description', 300)->nullable();
            // null = بدون سعر (تظهر "—" في الواجهة).
            $table->decimal('price', 8, 2)->nullable();
            // true = السعر "يبدأ من" وليس سعراً ثابتاً.
            $table->boolean('price_from')->default(false);
            // نص حر مثل "يومين" أو "ساعة".
            $table->string('duration', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_store_services');
    }
};