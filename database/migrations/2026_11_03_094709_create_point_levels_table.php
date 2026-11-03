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
        Schema::create('point_levels', function (Blueprint $table) {
        $table->id();
        // معرّف ثابت يعتمد عليه الكود (مثال: beginner) - لا يتغير بعد الإنشاء
        $table->string('point_level_key', 45)->unique();
        $table->string('point_level_name_en', 100);
        $table->string('point_level_name_ar', 100);
        // أقل عدد نقاط للوصول لهذا المستوى - لازم يكون في مستوى واحد دائماً حده 0
        $table->unsignedInteger('point_level_min_points')->unique();
        $table->tinyInteger('point_level_sort_order')->default(0);
        $table->timestamps();
        });
    
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_levels');
    }
};
