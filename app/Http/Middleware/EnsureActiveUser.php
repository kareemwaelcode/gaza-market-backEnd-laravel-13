<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * يطلّع المستخدم الموقوف (أو غير النشط) فوراً من أي session driver.
 *
 * الـ guard بيحمّل المستخدم من جدول users بكل طلب، فحالته هون دايماً طازجة؛
 * ما بنعتمد على حذف صفوف جدول sessions (اللي بتشتغل بس مع driver الداتابيز).
 * الزائر (بدون مستخدم) بيعدّي بدون تدخل، ومسؤولية 401 له على middleware auth.
 */
class EnsureActiveUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // العمود NOT NULL (default active) ومحمّل من الداتابيز بكل طلب حقيقي؛ الـ ?? بس لنموذج محفوظ بالذاكرة بدون refresh.
        if ($user !== null && ($user->user_status ?? 'active') !== 'active') {
            Auth::guard('web')->logout();

            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }

            return response()->json(['code' => 'UNAUTHENTICATED'], 401);
        }

        return $next($request);
    }
}