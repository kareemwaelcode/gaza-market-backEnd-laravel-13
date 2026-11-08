<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePlaceRequest;
use App\Models\Area;
use App\Models\Place;
use App\Models\StoreCategory;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class MarketPlaceController extends Controller
{
public function store(StorePlaceRequest $request): JsonResponse
{
    $user = $request->user();
    $existing = Place::where('user_id', $user->id)->first();

    if ($existing !== null && $existing->place_status !== Place::STATUS_REJECTED) {
        return $this->failure('ALREADY_HAS_PLACE', 409);
}

        $taken = $this->takenField($request->input('phone'), $request->input('whatsapp'), $existing?->id);

        if ($taken !== null) {
            return $this->failure('NUMBER_TAKEN', 409, ['field' => $taken]);
        }

        $storedPath = null;
        $oldLogo = $existing?->place_logo;

        try {
            $place = DB::transaction(function () use ($request, $user, $existing, &$storedPath) {
                $place = $existing ?? new Place;
                $place->fill([
                    'place_name' => trim($request->input('name')),
                    'place_type' => $request->input('type'),
                    'place_address' => trim($request->input('address')),
                    'place_phone' => $request->input('phone'),
                    'place_whatsapp' => $request->input('whatsapp'),
                    'store_category_id' => $this->storeCategoryId($request),
                    'area_id' => Area::where('area_slug', $request->input('region'))->value('id'),
                ]);
                $place->place_status = Place::STATUS_PENDING;
                $place->place_approved_at = null;

                if ($existing === null) {
                    $place->user_id = $user->id;
                    $place->place_slug = $this->newSlug();
                }

                $place->save();

                if ($request->hasFile('image')) {
                    $storedPath = $request->file('image')->store("places/{$place->id}", 'public');

                    if ($storedPath === false) {
                        throw new \RuntimeException('Failed to store place image');
                    }

                    $place->place_logo = $storedPath;
                    $place->save();
                }

                return $place;
            });
        } catch (UniqueConstraintViolationException) {
            $this->deleteFiles($storedPath);

            return $this->conflictResponse($user->id, $request->input('phone'));
        } catch (Throwable $e) {
            $this->deleteFiles($storedPath);
            report($e);

            return $this->failure('SERVER', 500);
        }

        if ($storedPath !== null && $oldLogo !== null && $oldLogo !== $storedPath) {
            Storage::disk('public')->delete($oldLogo);
        }

        return response()->json([
            'data' => [
                'id' => $place->id,
                'status' => $place->place_status,
            ],
        ], $existing === null ? 201 : 200);
    }

    public function mine(Request $request): JsonResponse
    {
        $place = Place::with('storeCategory:id,store_category_name_en')
            ->where('user_id', $request->user()->id)
            ->first();

        if ($place === null) {
            return response()->json(['data' => null]);
        }

        return response()->json([
            'data' => [
                'id' => $place->id,
                'status' => $place->place_status,
                'name' => $place->place_name,
                'type' => $place->place_type,
                'sub_category' => $place->storeCategory?->store_category_name_en,
                'plan' => $place->place_plan,
                'activated' => $place->place_activated_at !== null,
            ],
        ]);
    }

    private function storeCategoryId(StorePlaceRequest $request): ?int
    {
        if ($request->input('type') !== 'store') {
            return null;
        }

        return StoreCategory::where('store_category_name_en', $request->input('sub_category'))->value('id');
    }

    private function takenField(string $phone, string $whatsapp, ?int $ignoreId): ?string
    {
        $exists = fn (string $column, string $value) => Place::where($column, $value)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();

        if ($exists('place_phone', $phone)) {
            return 'phone';
        }

        if ($exists('place_whatsapp', $whatsapp)) {
            return 'whatsapp';
        }

        return null;
    }

    private function conflictResponse(int $userId, string $phone): JsonResponse
    {
        if (Place::where('user_id', $userId)->exists()) {
            return $this->failure('ALREADY_HAS_PLACE', 409);
        }

        $field = Place::where('place_phone', $phone)->exists() ? 'phone' : 'whatsapp';

        return $this->failure('NUMBER_TAKEN', 409, ['field' => $field]);
    }

    private function newSlug(): string
    {
        do {
            $slug = 'p-'.Str::lower(Str::random(12));
        } while (Place::where('place_slug', $slug)->exists());

        return $slug;
    }

    private function deleteFiles(?string $path): void
    {
        if ($path !== null) {
            Storage::disk('public')->delete($path);
        }
    }

    private function failure(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['code' => $code], $extra), $status);
    }
}