<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_tables', function (Blueprint $table) {
            $table->id();
            // الطاولة تابعة للمحل: حذف المحل يحذف طاولاته.
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            $table->string('name', 40);
            // null = عدد الكراسي غير محدد.
            $table->unsignedSmallInteger('seats')->nullable();
            // علم يدوي بيد المالك، غير مرتبط بوقت.
            $table->enum('status', ['available', 'occupied'])->default('available');
            $table->timestamps();
            // حذف ناعم: الحجوزات القديمة تبقى مرتبطة بالطاولة وتظهر "طاولة محذوفة".
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_tables');
    }
};