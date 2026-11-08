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
    Schema::create('saved_listings', function (Blueprint $table) {
    $table->id();
    // الحفظ بلا قيمة بدون صاحبه أو إعلانه، فبينحذف معهم
    $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
    $table->foreignId('listing_id')->constrained('listings')->cascadeOnDelete();
    // وقت الحفظ لترتيب "محفوظاتي" من الأحدث للأقدم (بدون updated_at لأن الحفظ ما بيتعدل)
    $table->dateTime('saved_listing_created_at')->useCurrent();
    // نفس المستخدم ما بيحفظ نفس الإعلان مرتين، وهو كمان فهرس استعلام "محفوظات المستخدم"
    $table->unique(['user_id', 'listing_id'], 'saved_listings_user_listing_unique');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('saved_listings');
    }
};
