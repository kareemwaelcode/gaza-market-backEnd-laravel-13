<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\SubmitSubscriptionRequestRequest;
use App\Models\DashboardSubscriber;
use App\Models\DashboardSubscriptionRequest;
use App\Models\Place;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/** إرسال طلب اشتراك من الزبون (يتطلب تسجيل دخول) إلى مساحة عمل معتمدة. */
class SubscriptionRequestController extends Controller
{
public function store(SubmitSubscriptionRequestRequest $request, string $slug): JsonResponse
{
    $place = Place::approved()->where('place_slug', $slug)->first();

    if ($place === null || ! DashboardSubscriber::supportsPlace($place)) {
        return $this->failure('NOT_FOUND', 404);
    }

    $user = $request->user();

    if ((int) $place->user_id === (int) $user->id) {
        return $this->failure('OWN_PLACE', 403);
    }

    $created = DB::transaction(function () use ($request, $place, $user) {
        // قفل صف المستخدم يسلسل طلباته، فلا يتجاوز طلبان متزامنان فحص التكرار.
        User::whereKey($user->id)->lockForUpdate()->first();

        $hasPending = DashboardSubscriptionRequest::where('place_id', $place->id)
            ->where('user_id', $user->id)
            ->where('status', DashboardSubscriptionRequest::STATUS_PENDING)
            ->exists();

        if ($hasPending) {
            return null;
        }

        $subscriptionRequest = new DashboardSubscriptionRequest($request->requestData());
        $subscriptionRequest->place_id = $place->id;
        $subscriptionRequest->user_id = $user->id;
        $subscriptionRequest->status = DashboardSubscriptionRequest::STATUS_PENDING;
        $subscriptionRequest->save();

        return $subscriptionRequest;
    });

    if ($created === null) {
        return $this->failure('DUPLICATE_PENDING', 409);
    }

    return response()->json(['data' => [
        'id' => (string) $created->id,
        'status' => $created->status,
        'createdAt' => $created->refresh()->created_at->toIso8601ZuluString('millisecond'),
    ]], 201);
}

private function failure(string $code, int $status): JsonResponse
{
    return response()->json(['code' => $code], $status);
}
}