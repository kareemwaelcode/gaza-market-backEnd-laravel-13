<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CategoryGroupController;
use App\Http\Controllers\Api\ListingCategoryController;
use App\Http\Controllers\Api\OptionController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\StoreCategoryGroupController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Support\Facades\Route;

// Route::get('/user', function (Request $request) {
//     return $request->user();
// })->middleware('auth:api');


    Route::apiResource('regions', RegionController::class)->only('index');
    Route::apiResource('units', UnitController::class)->only('index');
    Route::apiResource('category-groups', CategoryGroupController::class)->only('index');
    Route::apiResource('store-category-groups', StoreCategoryGroupController::class)->only('index');
    Route::apiResource('listing-categories', ListingCategoryController::class)->only('index');
    Route::apiResource('options', OptionController::class)->only('index');
    Route::apiResource('areas', AreaController::class)->only('index');
    Route::apiResource('categories', CategoryController::class)->only('index');