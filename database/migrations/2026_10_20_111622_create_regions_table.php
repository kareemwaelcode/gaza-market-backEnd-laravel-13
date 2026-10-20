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
        Schema::create('regions', function (Blueprint $table) {
            $table->id();
            $table->string("region_slug", 45)->nullable()->unique();
            $table->string("region_name_en", 45)->nullable();
            $table->string("region_name_ar", 45)->nullable();
            $table->tinyInteger("region_sort_order")->nullable(); // رمز المنطقة 
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('regions');
    }
};
