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
        Schema::create('units', function (Blueprint $table) {
        $table->id();
        $table->string("unit_slug", 45)->unique();
        $table->string("unit_name_en", 45);
        $table->string("unit_name_ar", 45);
        $table->boolean("unit_is_active")->default(true);
        $table->tinyInteger("unit_sort_order")->nullable();
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('units');
    }
};
