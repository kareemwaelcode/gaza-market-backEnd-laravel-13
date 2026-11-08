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
        Schema::create('places', function (Blueprint $table) {
            $table->id();
            $table->string('place_name', 80);
            $table->enum('place_type', ['workspace', 'cafe', 'restaurant', 'restaurant-cafe', 'store']);
            $table->string('place_address', 255);
            $table->string('place_phone', 10)->unique();
            $table->string('place_whatsapp', 15)->unique();
            $table->string('place_logo', 255)->nullable();
            $table->enum('place_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->enum('place_plan', ['free', 'paid'])->default('free');
            $table->boolean('place_is_open')->default(true);
            $table->string('place_slug', 32)->unique();
            $table->dateTime('place_approved_at')->nullable();
            $table->dateTime('place_activated_at')->nullable();
            $table->foreignId('store_category_id')->nullable()->constrained('store_categories')->restrictOnDelete();
            $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->timestamps();
            $table->index(['place_status', 'area_id'], 'places_status_area_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('places');
    }
};
