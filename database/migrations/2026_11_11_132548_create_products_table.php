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
    Schema::create('products', function (Blueprint $table) {
        $table->id();
        $table->string('product_name_ar', 100)->nullable();
        $table->string('product_name_en', 100)->nullable();
        $table->foreignId('category_id')->constrained()->restrictOnDelete();
        $table->decimal('product_quantity', 10, 2);
        $table->foreignId('unit_id')->constrained()->restrictOnDelete();
        $table->decimal('product_price', 10, 2);
        $table->string('product_store_name', 100);
        $table->foreignId('area_id')->constrained()->restrictOnDelete();
        $table->string('product_store_address', 150)->nullable();
        $table->string('product_store_phone', 20)->nullable();
        $table->string('product_receipt_image')->nullable();
        $table->enum('product_status', ['pending', 'active', 'hidden'])->default('pending');
        $table->foreignId('user_id')->constrained()->restrictOnDelete();
        $table->timestamps();

        $table->index(['product_status', 'category_id']);
        $table->index(['product_status', 'area_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
