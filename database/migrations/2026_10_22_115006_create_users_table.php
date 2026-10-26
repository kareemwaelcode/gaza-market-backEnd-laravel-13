<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
public function up(): void
{
Schema::create('users', function (Blueprint $table) {
    $table->id();
    $table->string('user_phone', 45)->unique();
    $table->string('user_full_name', 45);
    $table->string('user_password', 255)->nullable();
    $table->enum('user_role', ['user', 'store_owner'])->default('user');
    $table->enum('user_status', ['active', 'suspended', 'deleted'])->default('active');
    $table->enum('user_preferred_lang', ['en', 'ar'])->default('ar');
    $table->enum('user_theme', ['light', 'dark'])->default('light');
    $table->dateTime('user_phone_verified_at')->nullable();
    $table->dateTime('user_last_login_at')->nullable();
    $table->foreignId('area_id')->nullable()->constrained('areas')->restrictOnDelete();
    $table->unsignedInteger('user_points_total')->default(0);
    $table->string('user_avatar', 255)->nullable();
    $table->dateTime('user_created_at')->nullable();
    $table->dateTime('user_deleted_at')->nullable();
    $table->timestamp('updated_at')->nullable();
});

Schema::create('sessions', function (Blueprint $table) {
    $table->string('id')->primary();
    $table->foreignId('user_id')->nullable()->index();
    $table->string('ip_address', 45)->nullable();
    $table->text('user_agent')->nullable();  //نوع المتصفح والجهاز
    $table->longText('payload'); //البيانات المخزنة بالجلسة نفسها
    $table->integer('last_activity')->index(); //وقت آخر نشاط للجلسة، كرقم
});
}

public function down(): void
{
Schema::dropIfExists('sessions');
Schema::dropIfExists('users');
}
};