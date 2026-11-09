<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** يحمي مسارات لوحة صاحب المحل: مستخدم نشط، دور store_owner، ومحله معتمد (يُفحص من القاعدة بكل طلب). */
class EnsureStoreOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user === null) {
            return response()->json(['code' => 'UNAUTHENTICATED'], 401);
        }

        if (! $user->canUseOwnerDashboard()) {
            return response()->json(['code' => 'FORBIDDEN'], 403);
        }

        return $next($request);
    }
}