<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);
        Paginator::useBootstrap();

                // حفظ وإلغاء حفظ ومحفوظاتي: 60 طلب بالدقيقة لكل مستخدم
        RateLimiter::for('saved-listings', fn (Request $request) => Limit::perMinute(60)
            ->by('saved-listings:'.($request->user()?->id ?? $request->ip())));

        RateLimiter::for('places', fn (Request $request) => Limit::perMinute(10)
            ->by('places:'.($request->user()?->id ?? $request->ip())));
    }
}