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
    Schema::create('place_visits', function (Blueprint $table) {
        $table->id();
        $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
        $table->date('visit_date');
        $table->unsignedInteger('visits_count')->default(0);
        // صف واحد لكل محل باليوم. يخدم الزيادة الذرية واستعلامات اليوم/الأسبوع.
        $table->unique(['place_id', 'visit_date'], 'place_visits_place_date_unique');
    });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('place_visits');
    }
};
