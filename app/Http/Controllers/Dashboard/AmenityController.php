<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UpdateAmenitiesRequest;
use App\Models\DashboardAmenity;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AmenityController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        return response()->json(['data' => $this->payload($place)]);
    }

    public function update(UpdateAmenitiesRequest $request): JsonResponse
    {
        $place = $this->ownPlace($request);
        $services = $request->enabledServices();

        if ($place->place_plan !== 'paid') {
            $locked = array_values(array_intersect(array_keys($services), DashboardAmenity::LOCKED_ON_FREE));

            if ($locked !== []) {
                return $this->failure('FEATURE_LOCKED', 403, ['services' => $locked]);
            }
        }

        DB::transaction(function () use ($place, $services) {
            $stale = DashboardAmenity::where('place_id', $place->id);

            if ($services !== []) {
                $stale->whereNotIn('service_key', array_keys($services));
            }

            $stale->delete();

            if ($services === []) {
                return;
            }

            $rows = [];

            foreach ($services as $key => $details) {
                $rows[] = [
                    'place_id' => $place->id,
                    'service_key' => $key,
                    'details' => $details,
                ];
            }

            DashboardAmenity::upsert($rows, ['place_id', 'service_key'], ['details']);
        }, 3);

        return response()->json(['data' => $this->payload($place)]);
    }

    private function ownPlace(Request $request): Place
    {
        return Place::where('user_id', $request->user()->id)->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function payload(Place $place): array
    {
        $services = [];

        $rows = DashboardAmenity::where('place_id', $place->id)
            ->orderBy('id')
            ->get(['service_key', 'details']);

        foreach ($rows as $row) {
            $services[$row->service_key] = [
                'enabled' => true,
                'details' => $row->details ?? '',
            ];
        }

        return ['services' => $services === [] ? new \stdClass : $services];
    }

    /** @param  array<string, mixed>  $extra */
    private function failure(string $code, int $status, array $extra = []): JsonResponse
    {
        return response()->json(array_merge(['code' => $code], $extra), $status);
    }
}