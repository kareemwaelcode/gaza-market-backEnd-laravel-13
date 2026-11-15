<?php

use App\Http\Controllers\Dashboard\ItemCategoryController;
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
    });