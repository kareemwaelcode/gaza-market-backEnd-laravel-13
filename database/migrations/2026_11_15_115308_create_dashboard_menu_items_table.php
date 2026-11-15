<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_menu_items', function (Blueprint $table) {
            $table->id();
            // الصنف تابع للمحل: حذف المحل يحذف أصنافه (مثل dashboard_item_categories).
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            // التصنيف اختياري؛ حذفه يترك الصنف بلا تصنيف كما يفعل الفرونت.
            $table->foreignId('item_category_id')->nullable()
                ->constrained('dashboard_item_categories')->nullOnDelete();
            $table->string('name', 80);
            // السعر اختياري (صنف بلا سعر مسموح بالفرونت). DECIMAL للمال، لا نص ولا float.
            $table->decimal('price', 8, 2)->nullable();
            $table->boolean('is_available')->default(true);
            // مسار الملف على القرص public (مثل place_logo)، وليس base64.
            $table->string('image', 255)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_menu_items');
    }
};