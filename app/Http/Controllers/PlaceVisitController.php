<?php

namespace App\Http\Controllers;

use App\Models\PlaceVisit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlaceVisitController extends Controller
{
    private const PERIODS = ['today', '7', '30', 'all'];

    /** ترتيب المحلات حسب الزيارات (قراءة فقط). */
    public function index(Request $request)
    {
        $period = in_array($request->query('period'), self::PERIODS, true) ? $request->query('period') : '7';

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
            ->paginate(15)
            ->appends(['period' => $period]);

        return response()->view('cms.place_visit.index', compact('rows', 'period'));
    }
}