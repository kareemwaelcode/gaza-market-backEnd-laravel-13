<?php

use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AdminPlaceController;
use App\Http\Controllers\Api\AreaController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CategoryGroupController;
use App\Http\Controllers\Api\HeroSectionController;
use App\Http\Controllers\Api\ListingCategoryController;
use App\Http\Controllers\Api\ListingController;
use App\Http\Controllers\Api\NavigationItemController;
use App\Http\Controllers\Api\PlaceVisitController;
use App\Http\Controllers\Api\PointLevelController;
use App\Http\Controllers\Api\PointRuleController;
use App\Http\Controllers\Api\PointTransactionController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ProductPriceController;
use App\Http\Controllers\Api\RegionController;
use App\Http\Controllers\Api\StoreCategoryController;
use App\Http\Controllers\Api\StoreCategoryGroupController;
use App\Http\Controllers\Api\UnitController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:api');

Route::middleware('throttle:lookups')->group(function () {
    Route::apiResource('regions', RegionController::class);
    Route::apiResource('units', UnitController::class);
    Route::apiResource('areas', AreaController::class);
    Route::apiResource('category-groups', CategoryGroupController::class);
    Route::apiResource('store-category-groups', StoreCategoryGroupController::class);
    Route::apiResource('store-categories', StoreCategoryController::class);
    Route::apiResource('listing-categories', ListingCategoryController::class);
    Route::apiResource('categories', CategoryController::class);
    Route::apiResource('navigation-items', NavigationItemController::class);
    Route::apiResource('point-rules', PointRuleController::class)->only(['index', 'show', 'update']);
    Route::apiResource('point-levels', PointLevelController::class)->only(['index', 'show', 'update']);
    Route::apiResource('hero-sections', HeroSectionController::class);
});
Route::apiResource('users', UserController::class);
Route::apiResource('admin/places', AdminPlaceController::class);
Route::post('admins/login', [AdminController::class, 'login']);
Route::post('admins/logout', [AdminController::class, 'logout']);
Route::get('admins/me', [AdminController::class, 'me']);
Route::apiResource('admins', AdminController::class);
Route::apiResource('point-transactions', PointTransactionController::class);
Route::apiResource('place-visits', PlaceVisitController::class);

Route::apiResource('listings', ListingController::class)->only(['index', 'show', 'destroy']);
Route::post('listings/{id}/approve', [ListingController::class, 'approve'])->whereNumber('id');
Route::post('listings/{id}/reject', [ListingController::class, 'reject'])->whereNumber('id');

Route::apiResource('products', ProductController::class)->only(['index', 'show', 'destroy'])->whereNumber('product');
Route::get('products/{id}/receipt', [ProductController::class, 'receipt'])->whereNumber('id');
Route::post('products/{id}/approve', [ProductController::class, 'approve'])->whereNumber('id');
Route::post('products/{id}/hide', [ProductController::class, 'hide'])->whereNumber('id');
Route::post('products/{id}/unhide', [ProductController::class, 'unhide'])->whereNumber('id');

Route::apiResource('product-prices', ProductPriceController::class)->only(['index', 'show', 'destroy']);
Route::get('product-prices/{id}/receipt', [ProductPriceController::class, 'receipt'])->whereNumber('id');
Route::post('product-prices/{id}/approve', [ProductPriceController::class, 'approve'])->whereNumber('id');
Route::post('product-prices/{id}/reject', [ProductPriceController::class, 'reject'])->whereNumber('id');