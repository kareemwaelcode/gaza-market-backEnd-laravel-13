<?php

namespace App\Providers;

use App\Services\WhatsApp\LogWhatsAppSender;
use App\Services\WhatsApp\WhatsAppSendGuard;
use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WuapiWhatsAppSender;
use App\Support\CacheInvalidation;
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

CacheInvalidation::register();

RateLimiter::for('lookups', fn (Request $request) =>
    Limit::perMinute(60)->by('lookups:' . $request->ip())
);

RateLimiter::for('market-feed', fn (Request $request) =>
    Limit::perMinute(120)->by('market-feed:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('market-detail', fn (Request $request) =>
    Limit::perMinute(60)->by('market-detail:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('places-feed', fn (Request $request) =>
    Limit::perMinute(120)->by('places-feed:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('place-detail', fn (Request $request) =>
    Limit::perMinute(60)->by('place-detail:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('market-owner', fn (Request $request) =>
    Limit::perMinute(30)->by('market-owner:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('saved-listings', fn (Request $request) =>
    Limit::perMinute(60)
        ->by('saved-listings:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('places', fn (Request $request) =>
    Limit::perMinute(10)
        ->by('places:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('place-visit', fn (Request $request) =>
    Limit::perMinute(60)
        ->by('place-visit:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('prices-feed', fn (Request $request) =>
    Limit::perMinute(120)
        ->by('prices-feed:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('prices-my-votes', fn (Request $request) =>
    Limit::perMinute(120)
        ->by('prices-my-votes:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('product-prices', fn (Request $request) =>
    Limit::perMinute(10)
        ->by('product-prices:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('price-votes', fn (Request $request) =>
    Limit::perMinute(60)
        ->by('price-votes:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('my-prices', fn (Request $request) =>
    Limit::perMinute(60)
        ->by('my-prices:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('price-history', fn (Request $request) =>
    Limit::perMinute(60)
        ->by('price-history:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('points', fn (Request $request) =>
    Limit::perMinute(60)
        ->by('points:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('profile', fn (Request $request) =>
    Limit::perMinute(20)
        ->by('profile:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('profile-avatar', fn (Request $request) =>
    Limit::perMinute(10)
        ->by('profile-avatar:' . ($request->user()?->id ?? $request->ip()))
);

RateLimiter::for('dashboard', fn (Request $request) =>
    Limit::perMinute(30)
        ->by('dashboard:' . ($request->user()?->id ?? $request->ip()))
);


RateLimiter::for('dashboard-upload', fn (Request $request) =>
    Limit::perMinute(10)
        ->by('dashboard-upload:' . ($request->user()?->id ?? $request->ip()))
);
RateLimiter::for('subscription-requests', fn (Request $request) => [
    Limit::perMinute(5)
        ->by('subscription-requests:' . ($request->user()?->id ?? $request->ip())),

    Limit::perHour(30)
        ->by('subscription-requests-hour:' . ($request->user()?->id ?? $request->ip())),
]);

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