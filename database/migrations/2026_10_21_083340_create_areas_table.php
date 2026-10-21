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
    Schema::create('areas', function (Blueprint $table) {
    $table->id();
    $table->string("area_slug", 45)->unique();
    $table->string("area_name_en", 45);
    $table->string("area_name_ar", 45);
    $table->tinyInteger("area_sort_order")->nullable();
    $table->foreignId("region_id")->constrained("regions")->restrictOnDelete();
    $table->timestamps();
});
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('areas');
    }
};
