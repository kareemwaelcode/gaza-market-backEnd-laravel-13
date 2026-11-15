<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_item_categories', function (Blueprint $table) {
            $table->id();
            // التصنيف تابع للمحل: حذف المحل يحذف تصنيفاته (مثل place_visits).
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->enum('kind', ['menu', 'product', 'service']);
            $table->string('name', 45);
            $table->timestamps();

            // يمنع التكرار حتى مع الطلبات المتزامنة، ويخدم استعلام (المحل + النوع).
            $table->unique(['place_id', 'kind', 'name'], 'dashboard_item_categories_place_kind_name_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_item_categories');
    }
};