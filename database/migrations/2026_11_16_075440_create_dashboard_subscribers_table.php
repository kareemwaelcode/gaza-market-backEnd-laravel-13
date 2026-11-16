<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_subscribers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('country_code', 3);
            $table->string('whatsapp', 9);
            $table->enum('duration', ['day', 'week', 'month']);
            $table->decimal('amount', 8, 2)->default(0);
            $table->date('start_date');
            $table->string('note', 300)->nullable();
            $table->boolean('is_stopped')->default(false);
            $table->timestamps();
            $table->index(['place_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_subscribers');
    }
};