<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_workspace_pricing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->unique()->constrained('places')->cascadeOnDelete();
            $table->decimal('price_hour', 8, 2)->nullable();
            $table->decimal('price_half_day', 8, 2)->nullable();
            $table->decimal('price_full_day', 8, 2)->nullable();
            $table->decimal('price_week', 8, 2)->nullable();
            $table->decimal('price_month', 8, 2)->nullable();
            $table->time('open_time')->nullable();
            $table->time('close_time')->nullable();
            $table->unsignedSmallInteger('seats_total')->nullable();
            $table->unsignedSmallInteger('seats_available')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_workspace_pricing');
    }
};