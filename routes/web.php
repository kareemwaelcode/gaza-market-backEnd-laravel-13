<?php

use App\Http\Controllers\AdminController;
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


});