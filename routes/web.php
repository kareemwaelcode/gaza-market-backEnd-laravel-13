<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\Api\AppointmentController as ApiAppointmentController;
use App\Http\Controllers\Api\MarketController as ApiMarketController;
use App\Http\Controllers\Api\MyPriceController as ApiMyPriceController;
use App\Http\Controllers\Api\PlaceController as ApiPlaceController;
use App\Http\Controllers\Api\PointController as ApiPointController;
use App\Http\Controllers\Api\PublicProductController as ApiProductController;
use App\Http\Controllers\Api\PublicProductPriceController as ApiProductPriceController;
use App\Http\Controllers\Api\ProductPriceHistoryController as ApiProductPriceHistoryController;
use App\Http\Controllers\Api\ProfileController as ApiProfileController;
use App\Http\Controllers\Api\SubscriptionRequestController as ApiSubscriptionRequestController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CategoryGroupController;
use App\Http\Controllers\DashboardPlaceController;
use App\Http\Controllers\HeroSectionController;
use App\Http\Controllers\ListingCategoryController;
use App\Http\Controllers\ListingController;
use App\Http\Controllers\MarketListingController;
use App\Http\Controllers\MarketPlaceController;
use App\Http\Controllers\MarketPlaceVisitController;
use App\Http\Controllers\NavigationItemController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\PlaceController;
use App\Http\Controllers\PlaceVisitController;
use App\Http\Controllers\PointLevelController;
use App\Http\Controllers\PointRuleController;
use App\Http\Controllers\PointTransactionController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductPriceController;
use App\Http\Controllers\RegionsController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\RolePermissionController;
use App\Http\Controllers\SavedListingController;
use App\Http\Controllers\StoreCategoryController;
use App\Http\Controllers\StoreCategoryGroupController;
use App\Http\Controllers\UnitController;
use App\Http\Controllers\UserAuthController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\Website\HomeController;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

if (app()->environment('local')) {
    Route::redirect('/', '/cms/admins');
}
Route::prefix('cms/')->middleware('guest:admins')->group(function () {
    Route::get('{guard}/login', [UserAuthController::class, 'showLogin'])->where('guard', 'admins')->name('view.login');
    Route::post('{guard}/login', [UserAuthController::class, 'login'])->where('guard', 'admins')->middleware('throttle:admin-login');
});

Route::prefix('cms/admins')->middleware('auth:admins')->group(function () {
    Route::post('logout', [UserAuthController::class, 'logout'])->name('cms.logout');
});

Route::prefix('cms/admins')->middleware('auth:admins')->group(function () {
    Route::view('', 'cms.parent')->name('dashboard');
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

    Route::resource('place', PlaceController::class)->only(['index', 'show', 'destroy']);
    Route::post('place_approve/{id}', [PlaceController::class, 'approve'])->name('place_approve');
    Route::post('place_reject/{id}', [PlaceController::class, 'reject'])->name('place_reject');
    Route::post('place_subscribe/{id}', [PlaceController::class, 'subscribe'])->name('place_subscribe');
    Route::post('place_cancel_subscription/{id}', [PlaceController::class, 'cancelSubscription'])->name('place_cancel_subscription');

    Route::resource('place_visit', PlaceVisitController::class)->only(['index']);

    Route::resource('product', ProductController::class)->only(['index', 'show', 'destroy']);
    Route::get('product_receipt/{id}', [ProductController::class, 'receipt'])->name('product_receipt');
    Route::post('product_approve/{id}', [ProductController::class, 'approve'])->name('product_approve');
    Route::post('product_hide/{id}', [ProductController::class, 'hide'])->name('product_hide');
    Route::post('product_unhide/{id}', [ProductController::class, 'unhide'])->name('product_unhide');

    Route::resource('product_price', ProductPriceController::class)->only(['index', 'show', 'destroy']);
    Route::get('product_price_receipt/{id}', [ProductPriceController::class, 'receipt'])->name('product_price_receipt');
    Route::post('product_price_approve/{id}', [ProductPriceController::class, 'approve'])->name('product_price_approve');
    Route::post('product_price_reject/{id}', [ProductPriceController::class, 'reject'])->name('product_price_reject');

    Route::resource('roles', RoleController::class);
    Route::post('roles_update/{id}', [RoleController::class, 'update'])->name('roles_update');
    Route::resource('permissions', PermissionController::class);
    Route::post('permissions_update/{id}', [PermissionController::class, 'update'])->name('permissions_update');
    Route::resource('roles.permissions', RolePermissionController::class);
});


Route::prefix('gazamarket/')->group(function() {
    Route::get('index' , [HomeController::class, 'index'])->name('gazamarket.index');
    Route::get('home' , [HomeController::class, 'home'])->name('gazamarket.home');
});



Route::withoutMiddleware([StartSession::class, ShareErrorsFromSession::class, PreventRequestForgery::class])
    ->group(function () {
        Route::get('api/market', [ApiMarketController::class, 'index'])
            ->middleware('throttle:market-feed')
            ->name('market.index');

        Route::get('api/market/{listing}/similar', [ApiMarketController::class, 'similar'])
            ->where('listing', '[0-9]{1,18}')
            ->middleware('throttle:market-detail')
            ->name('market.similar');

        Route::get('api/market/{listing}', [ApiMarketController::class, 'show'])
            ->where('listing', '[0-9]{1,18}')
            ->middleware('throttle:market-detail')
            ->name('market.show');

        Route::get('api/places', [ApiPlaceController::class, 'index'])
            ->middleware('throttle:places-feed')
            ->name('places.index');

        Route::get('api/places/{slug}', [ApiPlaceController::class, 'show'])
            ->where('slug', 'p-[a-z0-9]{12}')
            ->middleware('throttle:place-detail')
            ->name('places.show');

        Route::get('api/products/search', [ApiProductController::class, 'search'])
            ->middleware('throttle:search-product')
            ->name('products.search');

        Route::get('api/products/{product}/prices', [ApiProductPriceHistoryController::class, 'index'])
            ->where('product', '[0-9]{1,18}')
            ->middleware('throttle:price-history')
            ->name('products.prices');

        Route::get('api/prices/ticker', [ApiProductPriceController::class, 'ticker'])
            ->middleware('throttle:prices-feed')
            ->name('prices.ticker');

        Route::get('api/prices', [ApiProductPriceController::class, 'index'])
            ->middleware('throttle:prices-feed')
            ->name('prices.index');
    });

Route::post('api/places/{place}/visit', [MarketPlaceVisitController::class, 'store'])
    ->where('place', '[0-9]{1,18}')
    ->middleware('throttle:place-visit')
    ->name('places.visit');

Route::prefix('api/auth')->name('auth.')->group(function () {
    Route::get('me', [AuthController::class, 'me'])
        ->middleware('active.user')
        ->name('me');

    Route::post('otp/request', [AuthController::class, 'requestOtp'])
        ->middleware('throttle:otp-request')
        ->name('otp.request');

    Route::post('otp/verify', [AuthController::class, 'verifyOtp'])
        ->middleware('throttle:otp-verify')
        ->name('otp.verify');

    Route::post('logout', [AuthController::class, 'logout'])->name('logout');

    Route::patch('me', [ApiProfileController::class, 'update'])
        ->middleware(['auth', 'active.user', 'throttle:profile'])
        ->name('me.update');

    Route::post('me/avatar', [ApiProfileController::class, 'avatar'])
        ->middleware(['auth', 'active.user', 'throttle:profile-avatar'])
        ->name('me.avatar');
});

Route::post('api/market', [MarketListingController::class, 'store'])
    ->middleware(['auth', 'active.user', 'throttle:create-market'])
    ->name('market.store');

Route::middleware(['auth', 'active.user', 'throttle:saved-listings'])
    ->prefix('api/market')
    ->name('market.saved.')
    ->group(function () {
        Route::get('saved', [SavedListingController::class, 'index'])->name('index');
        Route::post('{listing}/save', [SavedListingController::class, 'store'])->where('listing', '[0-9]{1,18}')->name('store');
        Route::delete('{listing}/save', [SavedListingController::class, 'destroy'])->where('listing', '[0-9]{1,18}')->name('destroy');
    });

Route::middleware(['auth', 'active.user', 'throttle:market-owner'])
    ->prefix('api/market')
    ->name('market.owner.')
    ->group(function () {
        Route::get('mine', [MarketListingController::class, 'mine'])->name('mine');
        Route::get('mine/{listing}', [MarketListingController::class, 'showMine'])->where('listing', '[0-9]{1,18}')->name('show');
        Route::match(['post', 'patch'], '{listing}', [MarketListingController::class, 'update'])->where('listing', '[0-9]{1,18}')->name('update');
        Route::delete('{listing}', [MarketListingController::class, 'destroy'])->where('listing', '[0-9]{1,18}')->name('destroy');
        Route::post('{listing}/sold', [MarketListingController::class, 'sold'])->where('listing', '[0-9]{1,18}')->name('sold');
    });

Route::middleware(['auth', 'active.user', 'throttle:places'])
    ->prefix('api/places')
    ->name('places.')
    ->group(function () {
        Route::post('', [MarketPlaceController::class, 'store'])->name('store');
        Route::get('mine', [MarketPlaceController::class, 'mine'])->name('mine');
    });

Route::post('api/places/{slug}/appointments', [ApiAppointmentController::class, 'store'])
    ->where('slug', 'p-[a-z0-9]{12}')
    ->middleware('throttle:appointments')
    ->name('places.appointments.store');

Route::post('api/places/{slug}/subscription-requests', [ApiSubscriptionRequestController::class, 'store'])
    ->where('slug', 'p-[a-z0-9]{12}')
    ->middleware(['auth', 'active.user', 'throttle:subscription-requests'])
    ->name('places.subscription-requests.store');

Route::post('api/products', [ApiProductController::class, 'store'])
    ->middleware(['auth', 'active.user', 'throttle:create-product'])
    ->name('products.store');

Route::get('api/prices/my-votes', [ApiProductPriceController::class, 'myVotes'])
    ->middleware(['auth', 'active.user', 'throttle:prices-my-votes'])
    ->name('prices.my-votes');

Route::post('api/prices', [ApiProductPriceController::class, 'store'])
    ->middleware(['auth', 'active.user', 'throttle:product-prices'])
    ->name('prices.store');

Route::post('api/prices/{price}/vote', [ApiProductPriceController::class, 'vote'])
    ->where('price', '[0-9]{1,18}')
    ->middleware(['auth', 'active.user', 'throttle:price-votes'])
    ->name('prices.vote');

Route::middleware(['auth', 'active.user'])
    ->prefix('api/me')
    ->name('me.')
    ->group(function () {
        Route::get('prices', [ApiMyPriceController::class, 'index'])->middleware('throttle:my-prices')->name('prices');
        Route::get('points', [ApiPointController::class, 'show'])->middleware('throttle:points')->name('points');
    });

Route::middleware(['active.user', 'store.owner', 'throttle:dashboard'])
    ->prefix('api/dashboard')
    ->name('owner.')
    ->group(function () {
        Route::get('place', [DashboardPlaceController::class, 'show'])->name('place.show');
        Route::patch('place', [DashboardPlaceController::class, 'update'])->name('place.update');
        Route::post('place/logo', [DashboardPlaceController::class, 'logo'])->middleware('throttle:dashboard-upload')->name('place.logo');
        Route::get('visits', [MarketPlaceVisitController::class, 'summary'])->name('visits.summary');
    });

require __DIR__ . '/dashboard.php';