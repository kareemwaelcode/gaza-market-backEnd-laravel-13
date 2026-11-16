<?php

namespace App\Http\Controllers;

use App\Jobs\SendPlaceStatusMessage;
use App\Models\Area;
use App\Models\Place;
use App\Models\PlaceSubscription;
use App\Models\User;
use App\Support\ApiCache;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PlaceController extends Controller
{
    private const PLAN_FILTERS = ['paid', 'free', 'expiring'];

    private const EXPIRING_WITHIN_DAYS = 3;

public function index(Request $request)
{
    $validator = validator($request->query(), [
        'status' => ['nullable', Rule::in(Place::STATUSES)],
        'type' => ['nullable', Rule::in(Place::TYPES)],
        'area' => ['nullable', 'integer'],
        'plan' => ['nullable', Rule::in(self::PLAN_FILTERS)],
    ]);

    if ($validator->fails() && $request->expectsJson()) {
        throw new ValidationException($validator);
    }

    $ignoredFilters = $validator->errors()->keys();
    $filters = collect($request->query())
        ->only(['status', 'type', 'area', 'plan'])
        ->except($ignoredFilters)
        ->all();

    $places = Place::query()
        ->with([
            'user:id,user_full_name,user_phone',
            'area:id,area_name_ar',
            'storeCategory:id,store_category_name_ar,store_category_name_en',
        ])
        ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('place_status', $status))
        ->when($filters['type'] ?? null, fn ($q, $type) => $q->where('place_type', $type))
        ->when($filters['area'] ?? null, fn ($q, $id) => $q->where('area_id', $id))
        ->when($filters['plan'] ?? null, fn ($q, $plan) => $this->applyPlanFilter($q, $plan))
        ->when(($filters['plan'] ?? null) === 'expiring', fn ($q) => $q->orderBy('place_plan_expires_at'))
        ->orderByRaw("case when place_status = 'pending' then 0 else 1 end")
        ->orderByDesc('id')
        ->paginate(15)
        ->appends($filters);

    $areas = Area::ordered()->get(['id', 'area_name_ar']);

    $planFilters = self::PLAN_FILTERS;

    $expiringDays = self::EXPIRING_WITHIN_DAYS;

    return response()->view('cms.place.index', compact('places', 'areas', 'filters', 'ignoredFilters', 'planFilters', 'expiringDays'));
}

public function show(string $id)
{
    $place = Place::with([
        'user',
        'area.region',
        'storeCategory',
        'subscriptions' => fn ($q) => $q->orderByDesc('id')->limit(10),
    ])->findOrFail($id);

    return response()->view('cms.place.show', compact('place'));
}

public function approve(string $id)
{
    $place = Place::findOrFail($id);

    $updated = DB::transaction(function () use ($place) {
        $updated = Place::whereKey($place->id)
            ->whereIn('place_status', [Place::STATUS_PENDING, Place::STATUS_REJECTED])
            ->update([
                'place_status' => Place::STATUS_APPROVED,
                'place_approved_at' => now(),
                'updated_at' => now(),
            ]);

        if ($updated) {
            User::whereKey($place->user_id)->where('user_role', 'user')->update(['user_role' => 'store_owner']);

            SendPlaceStatusMessage::dispatch($place->id, Place::STATUS_APPROVED);
        }

        return $updated;
    });

    if (! $updated) {
        return response()->json(['icon' => 'error', 'title' => 'Only pending or rejected places can be approved'], 409);
    }

    ApiCache::placesChanged();

    return response()->json(['redirect' => route('place.show', $place->id)], 200);
}

public function reject(string $id)
{
    $place = Place::findOrFail($id);
    $wasApproved = $place->place_status === Place::STATUS_APPROVED;

    $updated = DB::transaction(function () use ($place, $wasApproved) {
        $updated = Place::whereKey($place->id)
            ->whereIn('place_status', [Place::STATUS_PENDING, Place::STATUS_APPROVED])
            ->update([
                'place_status' => Place::STATUS_REJECTED,
                'place_approved_at' => null,
                'updated_at' => now(),
            ]);

        if ($updated) {

            if ($wasApproved) {
                User::whereKey($place->user_id)->where('user_role', 'store_owner')->update(['user_role' => 'user']);
            }

            SendPlaceStatusMessage::dispatch($place->id, Place::STATUS_REJECTED);
        }

        return $updated;
    });

    if (! $updated) {
        return response()->json(['icon' => 'error', 'title' => 'Only pending or approved places can be rejected'], 409);
    }

    ApiCache::placesChanged();

    return response()->json(['redirect' => route('place.show', $place->id)], 200);
}

public function subscribe(Request $request, string $id)
{
    $data = $request->validate([
        'notes' => ['nullable', 'string', 'max:255'],
    ]);

    $place = Place::findOrFail($id);

    if ($place->place_status !== Place::STATUS_APPROVED) {
        return response()->json(['icon' => 'error', 'title' => 'Only approved places can be subscribed'], 409);
    }

    // لا يوجد تسجيل دخول للأدمن بعد، فيُسجَّل activated_by = null إلى أن يُربط.
    PlaceSubscription::grantTo($place, null, $data['notes'] ?? null);

    ApiCache::placesChanged();

    return response()->json(['redirect' => route('place.show', $place->id)], 200);
}

public function cancelSubscription(string $id)
{
    $place = Place::findOrFail($id);

    if ($place->place_plan !== 'paid') {
        return response()->json(['icon' => 'error', 'title' => 'This place has no active subscription'], 409);
    }

    PlaceSubscription::cancelFor($place);

    ApiCache::placesChanged();

    return response()->json(['redirect' => route('place.show', $place->id)], 200);
}

public function destroy(string $id)
{
    $place = Place::findOrFail($id);

    try {
        $place->delete();
    } catch (QueryException $e) {
        // place_subscriptions.place_id هو restrictOnDelete: سجل الدفعات يمنع الحذف.
        if ((string) $e->getCode() !== '23000') {
            throw $e;
        }

        return response()->json(['icon' => 'error', 'title' => 'Cannot delete a place that has subscription records'], 409);
    }

    Storage::disk('public')->deleteDirectory("places/{$place->id}");

    return response()->json(['icon' => 'success', 'title' => 'Deleted Successfully'], 200);
}

/**
 * place_plan accessor لا يعمل داخل الاستعلام، فنقرأ الأعمدة مباشرة بنفس منطقه:
 * paid بلا تاريخ انتهاء تبقى paid، وpaid منتهية تُحسب free.
 */
private function applyPlanFilter($query, string $plan)
{
    $now = now();

    $activePaid = fn ($q) => $q->where('place_plan', 'paid')
        ->where(fn ($w) => $w->whereNull('place_plan_expires_at')->orWhere('place_plan_expires_at', '>', $now));

    return match ($plan) {
        'paid' => $query->where($activePaid),
        'free' => $query->where(fn ($q) => $q->where('place_plan', 'free')
            ->orWhere(fn ($w) => $w->where('place_plan', 'paid')
                ->whereNotNull('place_plan_expires_at')
                ->where('place_plan_expires_at', '<=', $now))),
        'expiring' => $query->where('place_plan', 'paid')
            ->where('place_plan_expires_at', '>', $now)
            ->where('place_plan_expires_at', '<=', $now->copy()->addDays(self::EXPIRING_WITHIN_DAYS)),
    };
}
}