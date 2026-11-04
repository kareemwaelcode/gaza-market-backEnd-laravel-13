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
Schema::create('point_transactions', function (Blueprint $table) {
    $table->id();
    // السجل المالي لا يُحذف مع المستخدم أو القاعدة
    $table->foreignId('user_id')->constrained('users')->restrictOnDelete();
    $table->foreignId('point_rule_id')->constrained('point_rules')->restrictOnDelete();
    // لقطة من نقاط القاعدة وقت المنح (قد تكون سالبة)
    $table->smallInteger('point_transaction_points');
    // مصدر النقاط؛ null للنقاط التي لا مصدر لها. source_id بلا FK لأنه يشير لعدة جداول
    $table->enum('point_transaction_source_type', ['product_price', 'price_vote', 'place', 'listing', 'product'])->nullable();
    $table->unsignedBigInteger('point_transaction_source_id')->nullable();
    // سجل ثابت: لا updated_at
    $table->dateTime('point_transaction_created_at')->useCurrent();

    // سجل نقاط مستخدم مرتب بالتاريخ
    $table->index(['user_id', 'point_transaction_created_at'], 'pt_user_created_idx');
    // فحص الحد اليومي: كم مرة أخذ المستخدم هذه القاعدة اليوم
    $table->index(['user_id', 'point_rule_id', 'point_transaction_created_at'], 'pt_user_rule_created_idx');
    // منع منح النقاط مرتين لنفس المستخدم/القاعدة/المصدر (NULL لا يتعارض)
    $table->unique(
        ['user_id', 'point_rule_id', 'point_transaction_source_type', 'point_transaction_source_id'],
        'pt_user_rule_source_unique'
    );
});
}

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('point_transactions');
    }
};
