<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('places', function (Blueprint $table) {
            // نهاية الباقة المدفوعة (UTC). null مع paid = باقة بلا انتهاء (محلات سابقة ضُبطت يدوياً)؛
            // مع free لا قيمة له. يُكتب من PlaceSubscription فقط، لا يدوياً.
            $table->dateTime('place_plan_expires_at')->nullable()->after('place_plan');

            // فلترة لوحة الأدمن: المشتركون / غير المشتركين / الأقرب انتهاءً.
            $table->index(['place_plan', 'place_plan_expires_at'], 'places_plan_expires_idx');
        });
    }

    public function down(): void
    {
        Schema::table('places', function (Blueprint $table) {
            $table->dropIndex('places_plan_expires_idx');
            $table->dropColumn('place_plan_expires_at');
        });
    }
};