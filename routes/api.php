<?php

use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CategoryGroupController;
use App\Http\Controllers\Api\HeroSectionController;
use App\Http\Controllers\Api\ListingCategoryController;
use App\Http\Controllers\Api\NavigationItemController;
use App\Http\Controllers\Api\PointLevelController;
use App\Http\Controllers\Api\PointRuleController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\StoreCategoryGroupController;
use App\Http\Controllers\Api\UnitController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');



// Route::apiResource('regions', RegionController::class)->only(['index', 'show']);
// Route::apiResource('regions', RegionController::class)
//     ->only(['store', 'update', 'destroy'])
//     ->middleware('auth:admins-api');

Route::apiResource('regions', RegionController::class);
Route::apiResource('units', UnitController::class);
Route::apiResource('areas', AreaController::class);
Route::apiResource('category-groups', CategoryGroupController::class);
Route::apiResource('store-category-groups', StoreCategoryGroupController::class);
Route::apiResource('listing-categories', ListingCategoryController::class);
Route::apiResource('categories', CategoryController::class);
Route::apiResource('navigation-items', NavigationItemController::class);
Route::apiResource('point-rules', PointRuleController::class)->only(['index', 'show', 'update']);
Route::apiResource('point-levels', PointLevelController::class)->only(['index', 'show', 'update']);
Route::apiResource('hero-sections', HeroSectionController::class);
