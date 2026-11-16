<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dashboard_notifications', function (Blueprint $table) {
            $table->id();
            // الإشعار تابع للمحل: حذف المحل يحذف إشعاراته.
            $table->foreignId('place_id')->constrained('places')->cascadeOnDelete();
            // مفتاح أيقونة من قائمة الفرونت (مثل bell, tag) وليس نصاً حراً طويلاً.
            $table->string('icon', 40);
            $table->string('title', 200);
            $table->string('sub', 200)->nullable();
            $table->boolean('is_read')->default(false);
            // created_at فقط: الإشعار لا يُعدَّل، والقراءة تغيّر is_read ولا تحتاج updated_at.
            $table->timestamp('created_at')->useCurrent();

            // القائمة (الأحدث أولاً) وتقليم ما فوق 30 لكل محل.
            $table->index(['place_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dashboard_notifications');
    }
};