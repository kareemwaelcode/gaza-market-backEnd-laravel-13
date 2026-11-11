<?php

namespace App\Http\Controllers;

use App\Models\Place;
use App\Models\PlaceVisit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * زيارات المحلات (للمنصة ولوحة المالك).
 * store: تسجيل زيارة (عام). summary: أرقام محل المالك فقط (store.owner).
 */
class MarketPlaceVisitController extends Controller
{
    /** نفس الزائر لا يُعدّ مرتين لنفس المحل خلال هذه المدة. */
    public const DEDUPE_MINUTES = 30;

    public function store(Request $request, string $place): JsonResponse
    {
        // محل غير معتمد أو غير موجود: 404 بنفس الرد حتى لا نكشف وجوده.
        $placeModel = Place::approved()->find($place);

        if ($placeModel === null) {
            return response()->json(['code' => 'NOT_FOUND'], 404);
        }

        $user = $request->user();

        // زيارة المالك لمحله لا تُعدّ.
        if ($user !== null && (int) $user->id === (int) $placeModel->user_id) {
            return response()->json(['data' => ['counted' => false]]);
        }

        $identity = $user !== null ? 'u'.$user->id : 'ip'.hash('sha256', (string) $request->ip());
        $key = "place-visit:{$placeModel->id}:{$identity}";

        // add() ترجع false إذا المفتاح موجود (زيارة حديثة)، وتنشئه بعملية واحدة.
        if (! Cache::add($key, 1, now()->addMinutes(self::DEDUPE_MINUTES))) {
            return response()->json(['data' => ['counted' => false]]);
        }

        PlaceVisit::record($placeModel->id);

        return response()->json(['data' => ['counted' => true]]);
    }

    public function summary(Request $request): JsonResponse
    {
        // المحل يُحدد من الجلسة فقط، ولا يُقبل معرّف محل من الطلب.
        $place = Place::where('user_id', $request->user()->id)->firstOrFail();

        return response()->json(['data' => PlaceVisit::summary($place->id)]);
    }
}