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
        Schema::create('price_votes', function (Blueprint $table) {
        $table->id();

        // الأصوات تابعة للسعر: بتنحذف معه
        $table->foreignId('product_price_id')->constrained('product_prices')->cascadeOnDelete();
        $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

        $table->enum('price_vote_type', ['confirm', 'report']);

        // الصوت ممكن يتبدّل (confirm <-> report) فنحتاج updated_at
        $table->timestamps();

        // صوت واحد لكل مستخدم على السعر: الحماية الفعلية من التكرار والسباق
        $table->unique(['product_price_id', 'user_id'], 'pv_price_user_unique');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('price_votes');
    }
};
