<?php

use App\Http\Middleware\EnsureStoreOwner;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'store.owner' => EnsureStoreOwner::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // مسارات api/* ترجع JSON دائماً (401 بدل redirect لصفحة login غير موجودة).
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $e) => $request->is('api/*') || $request->expectsJson()
        );
    })->create();