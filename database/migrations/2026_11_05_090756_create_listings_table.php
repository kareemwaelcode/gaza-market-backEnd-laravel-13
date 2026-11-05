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
    Schema::create('listings', function (Blueprint $table) {
    $table->id();
    $table->string('listing_title', 100);
    $table->enum('listing_condition', ['new', 'used', 'urgent']);
    $table->string('listing_description', 1000);    
    $table->decimal('listing_price', 10, 2);
    $table->boolean('listing_is_negotiable')->default(false);
    $table->string('listing_phone', 10);
    $table->string('listing_whatsapp', 15)->nullable();
    $table->enum('listing_status', ['pending', 'approved', 'rejected', 'sold'])->default('pending');
    $table->dateTime('listing_published_at')->nullable();
    $table->string('listing_rejection_reason', 255)->nullable();
    $table->foreignId('listing_category_id')->constrained('listing_categories')->restrictOnDelete();
    $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
    $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
    $table->timestamps();
    $table->index(['listing_status', 'listing_published_at'], 'listings_status_published_idx');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('listings');
    }
};
