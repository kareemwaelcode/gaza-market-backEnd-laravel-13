<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('place_subscriptions', function (Blueprint $table) {
            $table->id();
            // سجل الدفعات: لا يُحذف المحل ما دام له اشتراكات (الحذف الناعم هو المسار المعتاد).
            $table->foreignId('place_id')->constrained('places')->restrictOnDelete();
            // مبلغ الدفعة وقت التفعيل (لا يتأثر بتغيير السعر لاحقاً).
            $table->decimal('subscription_amount', 8, 2)->unsigned();
            $table->char('subscription_currency', 3);
            $table->unsignedTinyInteger('subscription_period_months');
            // UTC. العدّ يبدأ من لحظة التفعيل، والتجديد المبكر يبدأ من نهاية الباقة الحالية.
            $table->dateTime('subscription_starts_at');
            $table->dateTime('subscription_ends_at');
            $table->string('subscription_notes', 255)->nullable();
            // يمنع تكرار تذكير الانتهاء (المهمة المجدولة لاحقاً).
            $table->timestamp('subscription_reminder_sent_at')->nullable();
            // الإلغاء يُبقي السجل ولا يحذفه.
            $table->timestamp('subscription_cancelled_at')->nullable();
            // null حالياً: لوحة الـ CMS بلا تسجيل دخول مربوط بجدول admins. يُربط عند إضافة الدخول.
            $table->foreignId('subscription_activated_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->timestamps();

            // الاشتراك الفعّال للمحل وسجله (الأحدث أولاً).
            $table->index(['place_id', 'subscription_ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('place_subscriptions');
    }
};