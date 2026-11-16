<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_clinic_hours', function (Blueprint $table) {
            $table->id();
            // الدوام تابع للعيادة: حذف المحل يحذف دوامه.
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            // مفاتيح الفرونت sat..fri (لا رقم JS لليوم: أحد=0 والأسبوع عندنا يبدأ سبت). التحقق بالـ Request.
            $table->string('day_of_week', 3);
            $table->boolean('is_open')->default(true);
            // وقت بلا تاريخ (TIME)، بتوقيت Asia/Gaza المحلي، لا UTC ولا نص.
            $table->time('open_time')->default('09:00:00');
            $table->time('close_time')->default('17:00:00');
            // مدة الموعد: قيمة وحدة لكل العيادة (15/20/30/45/60) تُكتب بكل الصفوف.
            $table->unsignedSmallInteger('slot_minutes')->default(30);
            $table->timestamps();

            // يوم واحد لكل عيادة، ويخدم أيضاً قراءة دوام العيادة (بادئة place_id).
            $table->unique(['place_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_clinic_hours');
    }
};