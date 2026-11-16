<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_reservations', function (Blueprint $table) {
            $table->id();
            // الحجز تابع للمحل: حذف المحل يحذف حجوزاته.
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            // الطاولة تُحذف ناعماً فالسجل يبقى؛ null لحجز بلا طاولة.
            $table->foreignId('dashboard_table_id')->nullable()->constrained('dashboard_tables')->nullOnDelete();
            $table->string('name', 80);
            $table->string('country_code', 3);
            $table->string('whatsapp', 9);
            // يُخزَّن UTC (config/app.php) والعرض بتوقيت Asia/Gaza عند الفرونت.
            $table->dateTime('reserved_at');
            $table->unsignedSmallInteger('people_count')->nullable();
            $table->string('note', 300)->nullable();
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->timestamps();
            // حذف ناعم: الحجز المحذوف بعد إلغائه يبقى محسوباً ضمن حد الباقة.
            $table->softDeletes();

            // القائمة (الأحدث أولاً) بدون فلتر، ومع فلتر الحالة وعداد pending.
            $table->index(['place_id', 'created_at']);
            $table->index(['place_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_reservations');
    }
};