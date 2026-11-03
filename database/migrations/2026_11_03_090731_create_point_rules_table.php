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
        Schema::create('point_rules', function (Blueprint $table) {
        $table->id();
        // معرّف ثابت يعتمد عليه الكود (مثال: add_price) - لا يتغير بعد الإنشاء
        $table->string('point_rule_key', 45)->unique();
        $table->string('point_rule_name_en', 100);
        $table->string('point_rule_name_ar', 100);
        // قد تكون سالبة (خصم نقاط)
        $table->smallInteger('point_rule_points');
        // null = بلا حد يومي
        $table->unsignedSmallInteger('point_rule_daily_limit')->nullable();
        $table->boolean('point_rule_is_active')->default(true);
        $table->tinyInteger('point_rule_sort_order')->default(0);
        $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_rules');
    }
};
