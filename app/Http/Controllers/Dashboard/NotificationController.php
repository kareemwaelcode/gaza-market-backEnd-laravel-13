<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\DashboardNotification;
use App\Models\Place;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class NotificationController extends Controller
{
    /** آخر 30 إشعاراً كحد أقصى (بدون pagination لأن الحد ثابت بالمحل). */
    public function index(Request $request): JsonResponse
    {
        $place = $this->ownPlace($request);

        $rows = DashboardNotification::where('place_id', $place->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(DashboardNotification::MAX_PER_PLACE)
            ->get();

        return response()->json([
            'data' => $rows->map(fn (DashboardNotification $n) => $this->payload($n))->all(),
            'meta' => ['unread' => $rows->where('is_read', false)->count()],
        ]);
    }

    /** يعلّم كل إشعارات المحل كمقروءة (الفرونت يفتح اللوحة ويقرأ الكل). */
    public function markAllRead(Request $request): Response
    {
        $place = $this->ownPlace($request);

        DashboardNotification::where('place_id', $place->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        return response()->noContent();
    }

    /** مسح الكل: حذف فعلي لإشعارات المحل. */
    public function clear(Request $request): Response
    {
        $place = $this->ownPlace($request);

        DashboardNotification::where('place_id', $place->id)->delete();

        return response()->noContent();
    }

    private function ownPlace(Request $request): Place
    {
        return Place::where('user_id', $request->user()->id)->firstOrFail();
    }

    /** @return array<string, mixed> المعرّفات نصوص والوقت UTC حسب عقد الفرونت. */
    private function payload(DashboardNotification $n): array
    {
        return [
            'id' => (string) $n->id,
            'icon' => $n->icon,
            'title' => $n->title,
            'sub' => $n->sub ?? '',
            'isRead' => $n->is_read,
            'createdAt' => $n->created_at->toIso8601ZuluString('millisecond'),
        ];
    }
}