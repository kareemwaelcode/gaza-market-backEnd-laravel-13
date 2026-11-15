<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
Schema::create('dashboard_store_products', function (Blueprint $table) {
    $table->id();
    $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
    $table->foreignId('item_category_id')->nullable()
        ->constrained('dashboard_item_categories')->nullOnDelete();
    $table->string('name', 100);
    $table->decimal('price', 8, 2)->nullable();
    $table->boolean('is_available')->default(true);
    $table->boolean('has_discount')->default(false);
    $table->string('image', 255)->nullable();
    $table->string('details', 300)->nullable();
    $table->string('brand', 40)->nullable();
    $table->string('size', 20)->nullable();
    $table->string('color', 40)->nullable();
    $table->string('material', 40)->nullable();
    $table->json('extra')->nullable();
    $table->timestamps();
});
}

public function down(): void
{
Schema::dropIfExists('dashboard_store_products');
}
};