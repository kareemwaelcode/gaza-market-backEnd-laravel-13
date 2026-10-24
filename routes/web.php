<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryGroupController;
use App\Http\Controllers\RegionsController;
use App\Http\Controllers\StoreCategoryController;
use App\Http\Controllers\StoreCategoryGroupController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});



Route::view('/', 'cms.parent')->name('dashboard'); 
Route::view('/temp', 'cms.temp'); 



Route::prefix('cms/admins/')->group(function() {
Route::view('' , 'cms.parent');    
Route::resource('admin', AdminController::class);
Route::post('admins_update/{id}' , [AdminController::class, 'update'])->name('admins_update');

Route::resource('region', RegionsController::class);
Route::post('region_update/{id}' , [RegionsController::class, 'update'])->name('region_update');

Route::resource('area', AreaController::class);
Route::post('area_update/{id}' , [AreaController::class, 'update'])->name('area_update');

Route::resource('unit', UnitController::class);
Route::post('unit_update/{id}' , [UnitController::class, 'update'])->name('unit_update');

Route::resource('category_group', CategoryGroupController::class);
Route::post('category_group_update/{id}' , [CategoryGroupController::class, 'update'])->name('category_group_update');

Route::resource('category', CategoryController::class);
Route::post('category_update/{id}' , [CategoryController::class, 'update'])->name('category_update');

Route::resource('store_category_group', StoreCategoryGroupController::class);
Route::post('store_category_group_update/{id}' , [StoreCategoryGroupController::class, 'update'])->name('store_category_group_update');

Route::resource('store_category', StoreCategoryController::class);
Route::post('store_category_update/{id}' , [StoreCategoryController::class, 'update'])->name('store_category_update');

Route::resource('user', UserController::class)->only(['index', 'destroy']);
Route::post('user_toggle_status/{id}' , [UserController::class, 'toggleStatus'])->name('user_toggle_status');

}); 