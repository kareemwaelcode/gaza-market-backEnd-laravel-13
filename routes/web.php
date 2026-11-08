<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryGroupController;
use App\Http\Controllers\HeroSectionController;
use App\Http\Controllers\ListingCategoryController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\MarketListingController;
use App\Http\Controllers\NavigationItemController;
use App\Http\Controllers\PointLevelController;
use App\Http\Controllers\PointRuleController;
use App\Http\Controllers\PointTransactionController;
use App\Http\Controllers\RegionsController;
use App\Http\Controllers\SavedListingController;
use App\Http\Controllers\StoreCategoryController;
use App\Http\Controllers\StoreCategoryGroupController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'cms.parent')->name('dashboard');
Route::view('/temp', 'cms.temp');

Route::prefix('cms/admins/')->group(function () {
    Route::view('', 'cms.parent');

    Route::resource('admin', AdminController::class);
    Route::post('admins_update/{id}', [AdminController::class, 'update'])->name('admins_update');
    Route::resource('user', UserController::class)->only(['index', 'destroy']);
    Route::post('user_toggle_status/{id}', [UserController::class, 'toggleStatus'])->name('user_toggle_status');
    Route::resource('region', RegionsController::class);
    Route::post('region_update/{id}', [RegionsController::class, 'update'])->name('region_update');
    Route::resource('area', AreaController::class);
    Route::post('area_update/{id}', [AreaController::class, 'update'])->name('area_update');
    Route::resource('unit', UnitController::class);
    Route::post('unit_update/{id}', [UnitController::class, 'update'])->name('unit_update');
    Route::resource('category_group', CategoryGroupController::class);
    Route::post('category_group_update/{id}', [CategoryGroupController::class, 'update'])->name('category_group_update');
    Route::resource('category', CategoryController::class);
    Route::post('category_update/{id}', [CategoryController::class, 'update'])->name('category_update');
    Route::resource('store_category_group', StoreCategoryGroupController::class);
    Route::post('store_category_group_update/{id}', [StoreCategoryGroupController::class, 'update'])->name('store_category_group_update');
    Route::resource('store_category', StoreCategoryController::class);
    Route::post('store_category_update/{id}', [StoreCategoryController::class, 'update'])->name('store_category_update');
    Route::resource('point_rule', PointRuleController::class)->only(['index', 'edit']);
    Route::post('point_rule_update/{id}', [PointRuleController::class, 'update'])->name('point_rule_update');
    Route::resource('point_level', PointLevelController::class)->only(['index', 'edit']);
    Route::post('point_level_update/{id}', [PointLevelController::class, 'update'])->name('point_level_update');
    Route::resource('point_transaction', PointTransactionController::class)->only(['index']);
    Route::resource('hero_section', HeroSectionController::class)->only(['index', 'edit']);
    Route::post('hero_section_update/{id}', [HeroSectionController::class, 'update'])->name('hero_section_update');
    Route::resource('navigation_item', NavigationItemController::class)->only(['index', 'create', 'store', 'edit', 'destroy']);
    Route::post('navigation_item_update/{id}', [NavigationItemController::class, 'update'])->name('navigation_item_update');
    Route::resource('listing_category', ListingCategoryController::class)->only(['index', 'create', 'store', 'edit', 'destroy']);
    Route::post('listing_category_update/{id}', [ListingCategoryController::class, 'update'])->name('listing_category_update');
    Route::resource('listing', ListingController::class)->only(['index', 'show', 'destroy']);
    Route::post('listing_approve/{id}', [ListingController::class, 'approve'])->name('listing_approve');
    Route::post('listing_reject/{id}', [ListingController::class, 'reject'])->name('listing_reject');
});

/*
|--------------------------------------------------------------------------
| API السوق (للمنصة)  —  /api/market/...
|--------------------------------------------------------------------------
*/

// نشر إعلان جديد
Route::post('api/market', [MarketListingController::class, 'store'])->middleware('auth')->name('market.store');

// محفوظاتي: لازم مستخدم مسجّل، وحدّ طلبات لكل مستخدم
Route::middleware(['auth', 'throttle:saved-listings'])->prefix('api/market')->name('market.saved.')->group(function () {
        Route::get('saved', [SavedListingController::class, 'index'])->name('index');
        Route::post('{listing}/save', [SavedListingController::class, 'store'])->where('listing', '[0-9]{1,18}')->name('store');
        Route::delete('{listing}/save', [SavedListingController::class, 'destroy'])->where('listing', '[0-9]{1,18}')->name('destroy');
    });