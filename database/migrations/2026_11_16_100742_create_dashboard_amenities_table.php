<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_amenities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('service_key', 32);
            $table->string('details', 120)->nullable();
            $table->timestamps();

            $table->unique(['place_id', 'service_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_amenities');
    }
};