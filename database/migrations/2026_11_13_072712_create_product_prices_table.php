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
        Schema::create('product_prices', function (Blueprint $table) {
    $table->id();
    // الأسعار ما بتنحذف مع المنتج أو المنطقة أو المستخدم (المستخدم soft delete)
    $table->foreignId('product_id')->constrained('products')->restrictOnDelete();
    $table->foreignId('area_id')->constrained('areas')->restrictOnDelete();
    $table->foreignId('user_id')->constrained('users')->restrictOnDelete();

    // مال: DECIMAL بمنزلتين (شيكل + أغورات)، مش FLOAT
    $table->decimal('product_price_value', 10, 2);

    $table->string('product_price_store_name', 120);
    $table->string('product_price_store_address', 255)->nullable();
    $table->string('product_price_store_phone', 20)->nullable();

    // مسار على القرص الخاص (local): receipts/<hash>.<ext> أطول من 45 حرف
    $table->string('product_price_receipt_image', 255)->nullable();

    // flagged: وصل لأكثر من الحد الأعلى للبلاغات وينتظر مراجعة الأدمن
    $table->enum('product_price_status', ['pending', 'approved', 'rejected', 'flagged'])->default('pending');
    $table->string('rejection_reason', 255)->nullable();
    $table->timestamp('reviewed_at')->nullable();

    // عدّادات للـ feed: بتتحدّث داخل transaction التصويت (مصدرها الحقيقي جدول price_votes)
    $table->unsignedInteger('confirm_count')->default(0);
    $table->unsignedInteger('report_count')->default(0);

    $table->timestamps();

    // الـ feed العام: المعتمد، الأحدث أولاً
    $table->index(['product_price_status', 'created_at', 'id'], 'pp_status_created_idx');
    // الفلترة بالمنطقة
    $table->index(['area_id', 'product_price_status', 'created_at'], 'pp_area_status_created_idx');
    // أسعار منتج واحد مرتبة بالسعر
    $table->index(['product_id', 'product_price_status', 'product_price_value'], 'pp_product_status_value_idx');
    // "أسعاري"
    $table->index(['user_id', 'created_at'], 'pp_user_created_idx');
});
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('product_prices');
    }
};
