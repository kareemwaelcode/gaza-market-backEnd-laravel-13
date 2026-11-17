<?php

use App\Http\Controllers\Api\CategoryGroupController;
use App\Http\Controllers\Api\ListingCategoryController;
use App\Http\Controllers\Api\OptionController;
use App\Http\Controllers\Api\StoreCategoryGroupController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Support\Facades\Route;

// ملاحظة: بادئة /api تُضاف تلقائياً لهذا الملف، فلا تكتبها في المسارات.

/*
|--------------------------------------------------------------------------
| قوائم الاختيار (عام، بدون جلسة)
|--------------------------------------------------------------------------
*/
Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');


Route::middleware('throttle:lookups')->group(function () {
    Route::apiResource('units', UnitController::class)->only('index');
    Route::apiResource('category-groups', CategoryGroupController::class)->only('index');
    Route::apiResource('store-category-groups', StoreCategoryGroupController::class)->only('index');
    Route::apiResource('listing-categories', ListingCategoryController::class)->only('index');
    Route::apiResource('options', OptionController::class)->only('index');
});