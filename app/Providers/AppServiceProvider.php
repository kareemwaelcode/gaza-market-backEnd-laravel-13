<?php

namespace App\Providers;

use App\Services\WhatsApp\LogWhatsAppSender;
use App\Services\WhatsApp\WhatsAppSendGuard;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WuapiWhatsAppSender;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use InvalidArgumentException;

class AppServiceProvider extends ServiceProvider
{
public function register(): void
{
$this->app->bind(WhatsAppSender::class, function () {
    $driver = (string) config('whatsapp.driver');

    return match ($driver) {
        'log' => new LogWhatsAppSender(),

        'wuapi' => new WuapiWhatsAppSender(
            config('whatsapp.wuapi.base_url'),
            config('whatsapp.wuapi.api_key'),
            config('whatsapp.wuapi.account_id'),
            (int) config('whatsapp.wuapi.timeout', 10),
            WhatsAppSendGuard::fromConfig(),
        ),

        default => throw new InvalidArgumentException(
            "Unsupported WhatsApp driver [{$driver}]."
        ),
    };
});
}

public function boot(): void
{
Schema::defaultStringLength(191);
Paginator::useBootstrap();

RateLimiter::for('saved-listings', fn (Request $request) =>
    Limit::perMinute(60)
        ->by('saved-listings:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('places', fn (Request $request) =>
    Limit::perMinute(10)
        ->by('places:' . ($request->user()?->id ?? $request->ip()))
);

// تسجيل زيارة محل (جديد)
RateLimiter::for('place-visit', fn (Request $request) =>
    Limit::perMinute(60)
        ->by('place-visit:' . ($request->user()?->id ?? $request->ip()))
);

// تسجيل سعر منتج جديد
// RateLimiter::for('product-store', fn (Request $request) =>
//     Limit::perHour(10)
//         ->by('product-store:' . ($request->user()?->id ?? $request->ip()))
// );

// البحث عن منتج
// RateLimiter::for('product-search', fn (Request $request) =>
//     Limit::perMinute(30)
//         ->by('product-search:' . ($request->user()?->id ?? $request->ip()))
// );

RateLimiter::for('dashboard', fn (Request $request) =>
    Limit::perMinute(30)
        ->by('dashboard:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('dashboard-upload', fn (Request $request) =>
    Limit::perMinute(10)
        ->by('dashboard-upload:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('otp-request', fn (Request $request) => [
    Limit::perMinute(10)
        ->by('otp-request:ip:' . $request->ip()),

    Limit::perHour(10)
        ->by('otp-request:phone:' . hash(
            'sha256',
            (string) $request->input('phone')
        )),
]);

RateLimiter::for('otp-verify', fn (Request $request) => [
    Limit::perMinute(20)
        ->by('otp-verify:ip:' . $request->ip()),

    Limit::perHour(20)
        ->by('otp-verify:phone:' . hash(
            'sha256',
            (string) $request->input('phone')
        )),
]);
}
}