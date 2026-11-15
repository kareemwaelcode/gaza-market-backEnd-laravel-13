<?php

use App\Http\Controllers\Dashboard\ItemCategoryController;
use App\Http\Controllers\Dashboard\MenuItemController;
use App\Http\Controllers\Dashboard\StoreProductController;
use Illuminate\Support\Facades\Route;

/*
| مسارات لوحة صاحب المحل الجديدة (تُحمّل من routes/web.php).
| مسارات api/dashboard القديمة (place, visits) بقيت في web.php بدون تغيير.
| الحماية: مستخدم نشط + دور store_owner + محل معتمد، مع rate limit "dashboard".
*/

Route::middleware(['active.user', 'store.owner', 'throttle:dashboard'])
    ->prefix('api/dashboard')
    ->name('owner.')
    ->group(function () {
        Route::get('item-categories', [ItemCategoryController::class, 'index'])->name('item-categories.index');
        Route::post('item-categories', [ItemCategoryController::class, 'store'])->name('item-categories.store');
        Route::get('item-categories/{category}', [ItemCategoryController::class, 'show'])
            ->where('category', '[0-9]{1,18}')->name('item-categories.show');
        Route::patch('item-categories/{category}', [ItemCategoryController::class, 'update'])
            ->where('category', '[0-9]{1,18}')->name('item-categories.update');
        Route::delete('item-categories/{category}', [ItemCategoryController::class, 'destroy'])
            ->where('category', '[0-9]{1,18}')->name('item-categories.destroy');

        Route::get('menu-items', [MenuItemController::class, 'index'])->name('menu-items.index');
        Route::post('menu-items', [MenuItemController::class, 'store'])->name('menu-items.store');
        Route::get('menu-items/{item}', [MenuItemController::class, 'show'])
            ->where('item', '[0-9]{1,18}')->name('menu-items.show');
        Route::patch('menu-items/{item}', [MenuItemController::class, 'update'])
            ->where('item', '[0-9]{1,18}')->name('menu-items.update');
        Route::delete('menu-items/{item}', [MenuItemController::class, 'destroy'])
            ->where('item', '[0-9]{1,18}')->name('menu-items.destroy');
        Route::post('menu-items/{item}/image', [MenuItemController::class, 'image'])
            ->where('item', '[0-9]{1,18}')->middleware('throttle:dashboard-upload')->name('menu-items.image');
        Route::delete('menu-items/{item}/image', [MenuItemController::class, 'destroyImage'])
            ->where('item', '[0-9]{1,18}')->name('menu-items.image.destroy');

        Route::get('store-products', [StoreProductController::class, 'index'])->name('store-products.index');
        Route::post('store-products', [StoreProductController::class, 'store'])->name('store-products.store');
        Route::get('store-products/{product}', [StoreProductController::class, 'show'])
            ->where('product', '[0-9]{1,18}')->name('store-products.show');
        Route::patch('store-products/{product}', [StoreProductController::class, 'update'])
            ->where('product', '[0-9]{1,18}')->name('store-products.update');
        Route::delete('store-products/{product}', [StoreProductController::class, 'destroy'])
            ->where('product', '[0-9]{1,18}')->name('store-products.destroy');
        Route::post('store-products/{product}/image', [StoreProductController::class, 'image'])
            ->where('product', '[0-9]{1,18}')->middleware('throttle:dashboard-upload')->name('store-products.image');
        Route::delete('store-products/{product}/image', [StoreProductController::class, 'destroyImage'])
            ->where('product', '[0-9]{1,18}')->name('store-products.image.destroy');
    });