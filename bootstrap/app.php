<?php

use App\Http\Middleware\EnsureActiveUser;
use App\Http\Middleware\EnsureStoreOwner;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'active.user' => EnsureActiveUser::class,
            'store.owner' => EnsureStoreOwner::class,
        ]);

        // روتات الـ API اللي تعتمد على الجلسة (تسجيل الدخول، المستخدم، لوحة المحل).
        // الروتات العامة في api.php ما تستخدمه، فتبقى بدون جلسة وبدون كوكيز.
        $middleware->group('api-session', [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            PreventRequestForgery::class,
        ]);

        // زائر غير مسجّل على مسارات الـ CMS يروح لصفحة دخول الأدمن (مافي route اسمه login).
        $middleware->redirectGuestsTo(fn (Request $request) =>
            $request->is('cms/*')
                ? route('view.login', ['guard' => 'admins'])
                : null
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // مسارات api/* ترجع JSON دائماً (401 بدل redirect لصفحة login غير موجودة).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();