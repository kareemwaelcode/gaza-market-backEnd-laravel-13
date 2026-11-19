<?php

use App\Http\Controllers\Dashboard\AmenityController;
use App\Http\Controllers\Dashboard\AppointmentController;
use App\Http\Controllers\Dashboard\ClinicHoursController;
use App\Http\Controllers\Dashboard\ItemCategoryController;
use App\Http\Controllers\Dashboard\MenuItemController;
use App\Http\Controllers\Dashboard\ReservationController;
use App\Http\Controllers\Dashboard\AdController;
use App\Http\Controllers\Dashboard\NotificationController;
use App\Http\Controllers\Dashboard\StoreProductController;
use App\Http\Controllers\Dashboard\StoreServiceController;
use App\Http\Controllers\Dashboard\PlaceSubscriptionController;
use App\Http\Controllers\Dashboard\SubscriberController;
use App\Http\Controllers\Dashboard\SubscriptionRequestController;
use App\Http\Controllers\Dashboard\TableController;
use App\Http\Controllers\Dashboard\WorkspacePricingController;
use Illuminate\Support\Facades\Route;

Route::middleware(['active.user', 'store.owner', 'throttle:dashboard'])
    ->prefix('api/dashboard')
    ->name('owner.')
    ->group(function () {
        Route::get('item-categories', [ItemCategoryController::class, 'index'])->name('item-categories.index');
        Route::post('item-categories', [ItemCategoryController::class, 'store'])->name('item-categories.store');
        Route::get('item-categories/{category}', [ItemCategoryController::class, 'show'])->where('category', '[0-9]{1,18}')->name('item-categories.show');
        Route::patch('item-categories/{category}', [ItemCategoryController::class, 'update'])->where('category', '[0-9]{1,18}')->name('item-categories.update');
        Route::delete('item-categories/{category}', [ItemCategoryController::class, 'destroy'])->where('category', '[0-9]{1,18}')->name('item-categories.destroy');

        Route::get('menu-items', [MenuItemController::class, 'index'])->name('menu-items.index');
        Route::post('menu-items', [MenuItemController::class, 'store'])->name('menu-items.store');
        Route::get('menu-items/{item}', [MenuItemController::class, 'show'])->where('item', '[0-9]{1,18}')->name('menu-items.show');
        Route::patch('menu-items/{item}', [MenuItemController::class, 'update'])->where('item', '[0-9]{1,18}')->name('menu-items.update');
        Route::delete('menu-items/{item}', [MenuItemController::class, 'destroy'])->where('item', '[0-9]{1,18}')->name('menu-items.destroy');
        Route::post('menu-items/{item}/image', [MenuItemController::class, 'image'])->where('item', '[0-9]{1,18}')->middleware('throttle:dashboard-upload')->name('menu-items.image');
        Route::delete('menu-items/{item}/image', [MenuItemController::class, 'destroyImage'])->where('item', '[0-9]{1,18}')->name('menu-items.image.destroy');

        Route::get('store-products', [StoreProductController::class, 'index'])->name('store-products.index');
        Route::post('store-products', [StoreProductController::class, 'store'])->name('store-products.store');
        Route::get('store-products/{product}', [StoreProductController::class, 'show'])->where('product', '[0-9]{1,18}')->name('store-products.show');
        Route::patch('store-products/{product}', [StoreProductController::class, 'update'])->where('product', '[0-9]{1,18}')->name('store-products.update');
        Route::delete('store-products/{product}', [StoreProductController::class, 'destroy'])->where('product', '[0-9]{1,18}')->name('store-products.destroy');
        Route::post('store-products/{product}/image', [StoreProductController::class, 'image'])->where('product', '[0-9]{1,18}')->middleware('throttle:dashboard-upload')->name('store-products.image');
        Route::delete('store-products/{product}/image', [StoreProductController::class, 'destroyImage'])->where('product', '[0-9]{1,18}')->name('store-products.image.destroy');

        Route::get('store-services', [StoreServiceController::class, 'index'])->name('store-services.index');
        Route::post('store-services', [StoreServiceController::class, 'store'])->name('store-services.store');
        Route::get('store-services/{service}', [StoreServiceController::class, 'show'])->where('service', '[0-9]{1,18}')->name('store-services.show');
        Route::patch('store-services/{service}', [StoreServiceController::class, 'update'])->where('service', '[0-9]{1,18}')->name('store-services.update');
        Route::delete('store-services/{service}', [StoreServiceController::class, 'destroy'])->where('service', '[0-9]{1,18}')->name('store-services.destroy');

        Route::get('tables', [TableController::class, 'index'])->name('tables.index');
        Route::post('tables', [TableController::class, 'store'])->name('tables.store');
        Route::patch('tables/{table}', [TableController::class, 'update'])->where('table', '[0-9]{1,18}')->name('tables.update');
        Route::delete('tables/{table}', [TableController::class, 'destroy'])->where('table', '[0-9]{1,18}')->name('tables.destroy');

        Route::get('reservations', [ReservationController::class, 'index'])->name('reservations.index');
        Route::get('reservations/summary', [ReservationController::class, 'summary'])->name('reservations.summary');
        Route::patch('reservations/{reservation}', [ReservationController::class, 'update'])->where('reservation', '[0-9]{1,18}')->name('reservations.update');
        Route::delete('reservations/{reservation}', [ReservationController::class, 'destroy'])->where('reservation', '[0-9]{1,18}')->name('reservations.destroy');

        Route::get('subscribers', [SubscriberController::class, 'index'])->name('subscribers.index');
        Route::post('subscribers', [SubscriberController::class, 'store'])->name('subscribers.store');
        Route::patch('subscribers/{subscriber}', [SubscriberController::class, 'update'])->where('subscriber', '[0-9]{1,18}')->name('subscribers.update');
        Route::delete('subscribers/{subscriber}', [SubscriberController::class, 'destroy'])->where('subscriber', '[0-9]{1,18}')->name('subscribers.destroy');
        Route::post('subscribers/{subscriber}/toggle-stop', [SubscriberController::class, 'toggleStop'])->where('subscriber', '[0-9]{1,18}')->name('subscribers.toggle-stop');
        Route::post('subscribers/{subscriber}/renew', [SubscriberController::class, 'renew'])->where('subscriber', '[0-9]{1,18}')->name('subscribers.renew');

        Route::get('subscription-requests', [SubscriptionRequestController::class, 'index'])->name('subscription-requests.index');
        Route::post('subscription-requests/{subscriptionRequest}/accept', [SubscriptionRequestController::class, 'accept'])->where('subscriptionRequest', '[0-9]{1,18}')->name('subscription-requests.accept');
        Route::post('subscription-requests/{subscriptionRequest}/reject', [SubscriptionRequestController::class, 'reject'])->where('subscriptionRequest', '[0-9]{1,18}')->name('subscription-requests.reject');
        Route::delete('subscription-requests/{subscriptionRequest}', [SubscriptionRequestController::class, 'destroy'])->where('subscriptionRequest', '[0-9]{1,18}')->name('subscription-requests.destroy');

        Route::get('workspace-pricing', [WorkspacePricingController::class, 'show'])->name('workspace-pricing.show');
        Route::put('workspace-pricing', [WorkspacePricingController::class, 'update'])->name('workspace-pricing.update');

        Route::get('amenities', [AmenityController::class, 'show'])->name('amenities.show');
        Route::put('amenities', [AmenityController::class, 'update'])->name('amenities.update');

        Route::get('appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::get('appointments/summary', [AppointmentController::class, 'summary'])->name('appointments.summary');
        Route::patch('appointments/{appointment}', [AppointmentController::class, 'update'])->where('appointment', '[0-9]{1,18}')->name('appointments.update');
        Route::delete('appointments/{appointment}', [AppointmentController::class, 'destroy'])->where('appointment', '[0-9]{1,18}')->name('appointments.destroy');

        Route::get('clinic-hours', [ClinicHoursController::class, 'show'])->name('clinic-hours.show');
        Route::put('clinic-hours', [ClinicHoursController::class, 'update'])->name('clinic-hours.update');

        Route::get('ads', [AdController::class, 'index'])->name('ads.index');
        Route::post('ads', [AdController::class, 'store'])->name('ads.store');
        Route::get('ads/{ad}', [AdController::class, 'show'])->where('ad', '[0-9]{1,18}')->name('ads.show');
        Route::patch('ads/{ad}', [AdController::class, 'update'])->where('ad', '[0-9]{1,18}')->name('ads.update');
        Route::delete('ads/{ad}', [AdController::class, 'destroy'])->where('ad', '[0-9]{1,18}')->name('ads.destroy');

        Route::get('notifications', [NotificationController::class, 'index'])->name('notifications.index');
        Route::post('notifications/read', [NotificationController::class, 'markAllRead'])->name('notifications.read');
        Route::delete('notifications', [NotificationController::class, 'clear'])->name('notifications.clear');

        Route::get('subscription', [PlaceSubscriptionController::class, 'show'])->name('subscription.show');
    });