<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PlaceVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PlaceVisitController extends Controller implements HasMiddleware
{
    private const PERIODS = ['today', '7', '30', 'all'];

    public static function middleware(): array
    {
        return [
            new Middleware('auth:admins-api'),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $validator = validator($request->query(), [
            'period' => ['nullable', Rule::in(self::PERIODS)],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()->toArray(),
            ], 422);
        }

        $period = (string) ($request->query('period') ?? '7');
        $perPage = (int) ($request->query('per_page') ?? 15);

        $from = match ($period) {
            'today' => PlaceVisit::today(),
            '7' => PlaceVisit::weekStart(),
            '30' => now(PlaceVisit::TIMEZONE)->subDays(29)->toDateString(),
            default => null,
        };

        $rows = PlaceVisit::query()
            ->select('place_id', DB::raw('SUM(visits_count) as total'))
            ->when($from, fn ($q) => $q->where('visit_date', '>=', $from))
            ->groupBy('place_id')
            ->orderByDesc('total')
            ->orderBy('place_id')
            ->with(['place:id,place_name,place_type,area_id', 'place.area:id,area_name_ar'])
            ->paginate($perPage)
            ->appends(['period' => $period, 'per_page' => $perPage])
            // MySQL بيرجع SUM كنص، فبنثبّته رقم
            ->through(fn (PlaceVisit $row) => [
                'place_id' => (int) $row->place_id,
                'total' => (int) $row->total,
                'place' => $row->place,
            ]);

        return response()->json([
            'status' => true,
            'message' => 'Data of Place Visits',
            'period' => $period,
            'data' => $rows,
        ], 200);
    }
}