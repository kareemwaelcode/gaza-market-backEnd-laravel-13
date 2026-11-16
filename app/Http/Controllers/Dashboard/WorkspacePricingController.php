<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateWorkspacePricingRequest;
use App\Models\DashboardWorkspacePricing;
use App\Models\Place;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class WorkspacePricingController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        if (! DashboardWorkspacePricing::supportsPlace($place)) {
            return $this->failure('NOT_FOUND', 404);
        }

        $row = DashboardWorkspacePricing::where('place_id', $place->id)->first();

        return response()->json(['data' => $this->payload($row)]);
    }

    public function update(UpdateWorkspacePricingRequest $request): JsonResponse
    {
        $place = $this->ownPlace($request);
        $data = $request->pricingData();

        try {
            $row = $this->upsert($place, $data);
        } catch (UniqueConstraintViolationException) {
            $row = $this->upsert($place, $data);
        }

        return response()->json(['data' => $this->payload($row->refresh())]);
    }

    /** @param  array<string, mixed>  $data */
    private function upsert(Place $place, array $data): DashboardWorkspacePricing
    {
        return DB::transaction(function () use ($place, $data) {
            $row = DashboardWorkspacePricing::where('place_id', $place->id)->lockForUpdate()->first()
                ?? new DashboardWorkspacePricing;

            $row->fill($data);
            $row->place_id = $place->id;
            $row->save();

            return $row;
        });
    }

    private function ownPlace(Request $request): Place
    {
        return Place::where('user_id', $request->user()->id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(?DashboardWorkspacePricing $row): array
    {
        return [
            'priceHour' => $this->price($row?->price_hour),
            'priceHalfDay' => $this->price($row?->price_half_day),
            'priceFullDay' => $this->price($row?->price_full_day),
            'priceWeek' => $this->price($row?->price_week),
            'priceMonth' => $this->price($row?->price_month),
            'openTime' => $this->time($row?->open_time),
            'closeTime' => $this->time($row?->close_time),
            'seatsTotal' => $row?->seats_total,
            'seatsAvailable' => $row?->seats_available,
        ];
    }

    private function price(mixed $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    private function time(?string $value): ?string
    {
        return $value === null ? null : substr($value, 0, 5);
    }

    /** @param  array<string, mixed>  $extra */
    private function failure(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['code' => $code], $extra), $status);
    }
}