<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_appointments', function (Blueprint $table) {
            $table->id();
            // الموعد تابع للعيادة: حذف المحل يحذف مواعيده.
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('name', 80);
            $table->string('country_code', 3);
            $table->string('whatsapp', 9);
            // يُخزَّن UTC (config/app.php) والعرض بتوقيت Asia/Gaza عند الفرونت.
            $table->dateTime('scheduled_at');
            // سبب الزيارة (الفرونت: slice 100) وملاحظة (300).
            $table->string('reason', 100)->nullable();
            $table->string('note', 300)->nullable();
            $table->enum('status', ['pending', 'confirmed', 'cancelled'])->default('pending');
            $table->timestamps();
            // حذف ناعم: الموعد المحذوف بعد إلغائه يبقى محسوباً ضمن حد الباقة.
            $table->softDeletes();

            // القائمة (الأحدث أولاً) بدون فلتر، ومع فلتر الحالة وعداد pending.
            $table->index(['place_id', 'created_at']);
            $table->index(['place_id', 'status', 'created_at']);
            // فحص التعارض مع المواعيد المؤكدة حول وقت محدد.
            $table->index(['place_id', 'status', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_appointments');
    }
};