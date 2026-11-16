<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/** إشعار يظهر في جرس لوحة صاحب المحل (نص عربي جاهز، يُنشأ من الخادم). */
class DashboardNotification extends Model
{
    /** الحد الأقصى المحفوظ لكل محل (NOTIFICATIONS_MAX بالفرونت). */
    public const MAX_PER_PLACE = 30;

    /** لا updated_at: الإشعار لا يُعدَّل. */
    public const UPDATED_AT = null;

    protected $table = 'dashboard_notifications';

    /** place_id و is_read لا يدخلان هنا عمداً: يُحددان من الخادم فقط. */
    protected $fillable = ['icon', 'title', 'sub'];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'is_read' => 'boolean',
        ];
    }

    /** ينشئ إشعاراً للمحل ثم يحذف ما زاد عن 30 (الأقدم أولاً). */
    public static function record(int $placeId, string $icon, string $title, ?string $sub = null): self
    {
        return DB::transaction(function () use ($placeId, $icon, $title, $sub) {
            $notification = new self(['icon' => $icon, 'title' => $title, 'sub' => $sub]);
            $notification->place_id = $placeId;
            $notification->save();

            $excess = self::where('place_id', $placeId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->skip(self::MAX_PER_PLACE)
                ->take(PHP_INT_MAX)
                ->pluck('id');

            if ($excess->isNotEmpty()) {
                self::where('place_id', $placeId)->whereIn('id', $excess)->delete();
            }

            return $notification;
        }, 3);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
}