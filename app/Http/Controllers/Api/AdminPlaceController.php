<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Jobs\SendPlaceStatusMessage;
use App\Models\Place;
use App\Models\User;
use App\Support\ApiCache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class AdminPlaceController extends Controller implements HasMiddleware
{
    private const PLAN_FILTERS = ['paid', 'free', 'expiring'];

    private const EXPIRING_WITHIN_DAYS = 3;

    public static function middleware(): array
    {
        return [
            new Middleware('auth:admins-api'),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $validator = validator($request->query(), [
            'status' => ['nullable', Rule::in(Place::STATUSES)],
            'type' => ['nullable', Rule::in(Place::TYPES)],
            'area' => ['nullable', 'integer'],
            'plan' => ['nullable', Rule::in(self::PLAN_FILTERS)],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $filters = array_filter($validator->validated());

        $places = Place::query()
            ->with([
                'user:id,user_full_name,user_phone',
                'area:id,area_name_ar',
                'storeCategory:id,store_category_name_ar,store_category_name_en',
            ])
            ->when($filters['status'] ?? null, fn ($query, $status) => $query->where('place_status', $status))
            ->when($filters['type'] ?? null, fn ($query, $type) => $query->where('place_type', $type))
            ->when($filters['area'] ?? null, fn ($query, $id) => $query->where('area_id', $id))
            ->when($filters['plan'] ?? null, fn ($query, $plan) => $this->applyPlanFilter($query, $plan))
            ->when(($filters['plan'] ?? null) === 'expiring', fn ($query) => $query->orderBy('place_plan_expires_at'))
            ->orderByRaw("case when place_status = 'pending' then 0 else 1 end")
            ->orderByDesc('id')
            ->paginate(15)
            ->appends($filters)
            ->through(fn (Place $place) => $place->append(['logo_url', 'whatsapp_link']));

        return response()->json([
            'status' => true,
            'message' => 'Data of Places',
            'data' => $places,
        ], 200);
    }

    public function show(string $id): JsonResponse
    {
        $place = Place::with([
            'user:id,user_full_name,user_phone,user_status',
            'area.region',
            'storeCategory',
            'subscriptions' => fn ($query) => $query->orderByDesc('id')->limit(10),
        ])->findOrFail($id)->append(['logo_url', 'whatsapp_link']);

        return response()->json([
            'status' => true,
            'message' => 'Data of Place',
            'data' => $place,
        ], 200);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $validator = validator($request->all(), [
            'place_status' => ['required', Rule::in([Place::STATUS_APPROVED, Place::STATUS_REJECTED])],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'icon' => 'error',
                'title' => $validator->getMessageBag()->first(),
            ], 400);
        }

        $place = Place::findOrFail($id);
        $target = $request->input('place_status');

        $updated = $target === Place::STATUS_APPROVED
            ? $this->approve($place)
            : $this->reject($place);

        if (! $updated) {
            return response()->json([
                'icon' => 'error',
                'title' => $target === Place::STATUS_APPROVED
                    ? 'Only pending or rejected places can be approved'
                    : 'Only pending or approved places can be rejected',
            ], 409);
        }

        SendPlaceStatusMessage::dispatch($place->id, $target);

        ApiCache::placesChanged();

        return response()->json([
            'icon' => 'success',
            'title' => 'Updated Successfully',
        ], 200);
    }

    public function destroy(string $id): JsonResponse
    {
        $place = Place::findOrFail($id);

        try {
            $place->delete();
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            return response()->json([
                'icon' => 'error',
                'title' => 'Cannot delete a place that has subscription records',
            ], 409);
        }

        Storage::disk('public')->deleteDirectory("places/{$place->id}");

        return response()->json([
            'status' => true,
            'message' => 'Deleted Successfully',
        ], 200);
    }

    private function approve(Place $place): bool
    {
        return DB::transaction(function () use ($place) {
            $updated = Place::whereKey($place->id)
                ->whereIn('place_status', [Place::STATUS_PENDING, Place::STATUS_REJECTED])
                ->update([
                    'place_status' => Place::STATUS_APPROVED,
                    'place_approved_at' => now(),
                    'updated_at' => now(),
                ]);

            if ($updated) {
                User::whereKey($place->user_id)->where('user_role', 'user')->update(['user_role' => 'store_owner']);
            }

            return (bool) $updated;
        });
    }

    private function reject(Place $place): bool
    {
        $wasApproved = $place->place_status === Place::STATUS_APPROVED;

        return DB::transaction(function () use ($place, $wasApproved) {
            $updated = Place::whereKey($place->id)
                ->whereIn('place_status', [Place::STATUS_PENDING, Place::STATUS_APPROVED])
                ->update([
                    'place_status' => Place::STATUS_REJECTED,
                    'place_approved_at' => null,
                    'updated_at' => now(),
                ]);

            if ($updated && $wasApproved) {
                User::whereKey($place->user_id)->where('user_role', 'store_owner')->update(['user_role' => 'user']);
            }

            return (bool) $updated;
        });
    }

    private function applyPlanFilter(Builder $query, string $plan): Builder
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