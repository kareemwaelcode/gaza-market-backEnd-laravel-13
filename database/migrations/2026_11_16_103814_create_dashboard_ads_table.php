<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_ads', function (Blueprint $table) {
            $table->id();
            // الإعلان تابع للمحل: حذف المحل يحذف إعلاناته (مثل amenities و menu_items).
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            // قيمة من قائمة ثابتة في DashboardAd::TYPES، والتحقق بالـ Request.
            $table->string('type', 20);
            $table->string('title', 100);
            $table->string('details', 300)->nullable();
            // تاريخ الإعلان بلا وقت (DATE). معناه (نشر/انتهاء) غير محسوم بالفرونت.
            $table->date('date')->nullable();
            $table->string('link', 255)->nullable();
            // DECIMAL للمال، لا نص ولا float.
            $table->decimal('price_before', 8, 2)->nullable();
            $table->decimal('price_after', 8, 2)->nullable();
            $table->boolean('is_hidden')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_ads');
    }
};