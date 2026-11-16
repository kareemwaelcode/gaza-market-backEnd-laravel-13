<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\Place;
use App\Models\PlaceSubscription;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** اشتراك صاحب المحل: قراءة فقط. التفعيل والتجديد والإلغاء من الأدمن. */
class PlaceSubscriptionController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $place = Place::where('user_id', $request->user()->id)->firstOrFail();

        $isPaid = $place->place_plan === 'paid';
        $expires = $isPaid ? $place->place_plan_expires_at : null;

        $current = $isPaid
            ? PlaceSubscription::where('place_id', $place->id)
                ->whereNull('subscription_cancelled_at')
                ->where('subscription_ends_at', '>', now())
                ->orderByDesc('subscription_ends_at')
                ->first()
            : null;

        return response()->json(['data' => [
            'plan' => $place->place_plan,
            'expiresAt' => $expires?->toIso8601ZuluString('millisecond'),
            // أيام كاملة متبقية (يُقرَّب للأعلى: ما بقي ساعة = يوم). null بلا انتهاء.
            'daysLeft' => $expires === null ? null : (int) ceil(now()->diffInSeconds($expires, false) / 86400),
            // سعر ومدة التجديد الحاليان (يُنسخان إلى الدفعة وقت التفعيل). الواجهة تعرضهما بدل الأرقام المكتوبة.
            'price' => number_format((float) config('subscription.price'), 2, '.', ''),
            'currency' => (string) config('subscription.currency'),
            'periodMonths' => (int) config('subscription.period_months'),
            'current' => $current === null ? null : [
                'amount' => $current->subscription_amount,
                'currency' => $current->subscription_currency,
                'periodMonths' => $current->subscription_period_months,
                'startsAt' => $current->subscription_starts_at->toIso8601ZuluString('millisecond'),
                'endsAt' => $current->subscription_ends_at->toIso8601ZuluString('millisecond'),
            ],
        ]]);
    }
}