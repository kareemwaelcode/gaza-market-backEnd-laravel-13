<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\ListSubscriberRequest;
use App\Http\Requests\Dashboard\StoreSubscriberRequest;
use App\Http\Requests\Dashboard\UpdateSubscriberRequest;
use App\Models\DashboardSubscriber;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class SubscriberController extends Controller
{
public function index(ListSubscriberRequest $request): JsonResponse
{
$place = $this->ownPlace($request);

$query = DashboardSubscriber::where('place_id', $place->id);

$total = (clone $query)->count();

$page = $query
    ->orderByDesc('created_at')
    ->orderByDesc('id')
    ->cursorPaginate((int) ($request->query('limit') ?: config('dashboard_plan.subscribers.page_size')));

return response()->json([
    'data' => $page->getCollection()->map(fn (DashboardSubscriber $s) => $this->payload($s))->all(),
    'meta' => [
        'next_cursor' => $page->nextCursor()?->encode(),
        'has_more' => $page->hasMorePages(),
        'total' => $total,
    ],
]);
}

public function store(StoreSubscriberRequest $request): JsonResponse
{
$place = $this->ownPlace($request);

$subscriber = new DashboardSubscriber($request->subscriberData());
$subscriber->place_id = $place->id;
$subscriber->save();

return response()->json(['data' => $this->payload($subscriber->refresh())], 201);
}

public function update(UpdateSubscriberRequest $request, string $subscriber): JsonResponse
{
$found = $this->ownSubscriber($request, $subscriber);

if ($found === null) {
    return $this->failure('NOT_FOUND', 404);
}

$data = $request->subscriberData();

if ($data === []) {
    return $this->failure('VALIDATION', 422, [
        'errors' => ['body' => ['No updatable fields were sent.']],
    ]);
}

$found->fill($data)->save();

return response()->json(['data' => $this->payload($found->refresh())]);
}

public function destroy(Request $request, string $subscriber): JsonResponse|Response
{
$found = $this->ownSubscriber($request, $subscriber);

if ($found === null) {
    return $this->failure('NOT_FOUND', 404);
}

// حذف نهائي حسب الفرونت ("سيتم حذف المشترك نهائياً").
$found->delete();

return response()->noContent();
}

/** يبدّل الإيقاف/إعادة التفعيل. القفل يمنع طلبين متزامنين من إلغاء أثر بعضهما. */
public function toggleStop(Request $request, string $subscriber): JsonResponse
{
$found = DB::transaction(function () use ($request, $subscriber) {
    $locked = $this->ownSubscriber($request, $subscriber, lock: true);

    if ($locked === null) {
        return null;
    }

    $locked->is_stopped = ! $locked->is_stopped;
    $locked->save();

    return $locked;
});

if ($found === null) {
    return $this->failure('NOT_FOUND', 404);
}

return response()->json(['data' => $this->payload($found->refresh())]);
}

/** التجديد: البداية = اليوم بتوقيت غزة، ويُعاد التفعيل إن كان موقوفاً. */
public function renew(Request $request, string $subscriber): JsonResponse
{
$found = $this->ownSubscriber($request, $subscriber);

if ($found === null) {
    return $this->failure('NOT_FOUND', 404);
}

$found->start_date = Carbon::now(config('dashboard_plan.subscribers.timezone'))->toDateString();
$found->is_stopped = false;
$found->save();

return response()->json(['data' => $this->payload($found->refresh())]);
}

private function ownPlace(Request $request): Place
{
return Place::where('user_id', $request->user()->id)->firstOrFail();
}

/** المشترك يخص محل المالك فقط، والنشاط غير المدعوم يعامل كغير موجود. */
private function ownSubscriber(Request $request, string $id, bool $lock = false): ?DashboardSubscriber
{
$place = $this->ownPlace($request);

if (! DashboardSubscriber::supportsPlace($place)) {
    return null;
}

$query = DashboardSubscriber::where('place_id', $place->id)->whereKey((int) $id);

if ($lock) {
    $query->lockForUpdate();
}

return $query->first();
}

/** @return array<string, mixed> المعرّفات نصوص حسب عقد الفرونت. */
private function payload(DashboardSubscriber $s): array
{
return [
    'id' => (string) $s->id,
    'name' => $s->name,
    'countryCode' => $s->country_code,
    'whatsapp' => $s->whatsapp,
    'duration' => $s->duration,
    'amount' => (float) $s->amount,
    'startDate' => $s->start_date->toDateString(),
    'note' => $s->note ?? '',
    'stopped' => $s->is_stopped,
    'createdAt' => $s->created_at->toIso8601ZuluString('millisecond'),
];
}

/** @param  array<string, mixed>  $extra */
private function failure(string $code, int $status, array $extra = []): JsonResponse
{
return response()->json(array_merge(['code' => $code], $extra), $status);
}
}