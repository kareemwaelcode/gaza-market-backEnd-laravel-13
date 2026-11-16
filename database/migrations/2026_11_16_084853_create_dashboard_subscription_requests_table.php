<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_subscription_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name', 80);
            $table->string('country_code', 3);
            $table->string('whatsapp', 9);
            $table->enum('duration', ['day', 'week', 'month']);
            $table->date('start_date');
            $table->string('note', 300)->nullable();
            $table->enum('status', ['pending', 'accepted', 'rejected'])->default('pending');
            $table->timestamps();

            $table->index(['place_id', 'status', 'created_at']);
            $table->index(['user_id', 'place_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_subscription_requests');
    }
};