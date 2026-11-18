<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CategoryGroupController;
use App\Http\Controllers\Api\ListingCategoryController;
use App\Http\Controllers\Api\OptionController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\StoreCategoryGroupController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Support\Facades\Route;

// ملاحظة: Laravel بيضيف بادئة /api تلقائياً لكل المسارات بهالملف.

/*
|--------------------------------------------------------------------------
| عام (بدون تسجيل دخول): القراءة فقط
|--------------------------------------------------------------------------
*/

Route::apiResource('regions', RegionController::class)->only('index');
Route::apiResource('areas', AreaController::class)->only('index');
Route::apiResource('units', UnitController::class)->only('index');
Route::apiResource('category-groups', CategoryGroupController::class)->only('index');
Route::apiResource('categories', CategoryController::class)->only('index');
Route::apiResource('store-category-groups', StoreCategoryGroupController::class)->only('index');
Route::apiResource('listing-categories', ListingCategoryController::class)->only('index');
Route::apiResource('options', OptionController::class)->only('index');

/*
|--------------------------------------------------------------------------
| دخول الأدمن بالتوكن (Passport)
|--------------------------------------------------------------------------
*/

Route::post('admin/login', [AdminAuthController::class, 'login'])
    ->middleware('throttle:5,1')
    ->name('admin.login');

/*
|--------------------------------------------------------------------------
| للأدمن فقط (Bearer token): عرض سجل + إضافة + تعديل + حذف
|--------------------------------------------------------------------------
*/

Route::middleware('auth:admins-api')->group(function () {
    Route::post('admin/logout', [AdminAuthController::class, 'logout'])->name('admin.logout');

    $adminActions = ['show', 'store', 'update', 'destroy'];

    Route::apiResource('regions', RegionController::class)->only($adminActions);
    Route::apiResource('areas', AreaController::class)->only($adminActions);
    Route::apiResource('units', UnitController::class)->only($adminActions);
    Route::apiResource('category-groups', CategoryGroupController::class)->only($adminActions);
    Route::apiResource('categories', CategoryController::class)->only($adminActions);
    Route::apiResource('store-category-groups', StoreCategoryGroupController::class)->only($adminActions);
    Route::apiResource('listing-categories', ListingCategoryController::class)->only($adminActions);
});