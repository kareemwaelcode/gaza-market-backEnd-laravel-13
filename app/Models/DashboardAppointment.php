<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** موعد زبون في عيادة، يديره صاحب المحل من اللوحة. */
class DashboardAppointment extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_CANCELLED];

    /** الانتقالات المسموحة (من → إلى): نفس قاعدة الحجوزات. */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_CANCELLED],
    ];

    public const COUNTRY_CODES = ['970', '972'];

    protected $table = 'dashboard_appointments';

    /** place_id و status لا يدخلان هنا عمداً: يُحددان من الخادم فقط. */
    protected $fillable = ['name', 'country_code', 'whatsapp', 'scheduled_at', 'reason', 'note'];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'scheduled_at' => 'datetime',
        ];
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    /** المواعيد تخص العيادات فقط (متجر بتصنيف clinic). يتطلب تحميل علاقة storeCategory. */
    public static function supportsPlace(Place $place): bool
    {
        return $place->place_type === 'store'
            && in_array(
                $place->storeCategory?->store_category_variant_key ?? 'general',
                config('dashboard_plan.appointments.variants'),
                true
            );
    }

    /** null يعني بلا حد تجاري (الباقة المدفوعة). */
    public static function limitFor(?string $plan): ?int
    {
        return $plan === 'paid'
            ? null
            : (int) config('dashboard_plan.appointments.free_limit');
    }

    /** إجمالي ما استقبلته العيادة، والحذف الناعم لا يُنقصه. */
    public static function usedBy(int $placeId): int
    {
        return self::withTrashed()->where('place_id', $placeId)->count();
    }

    /**
     * يوجد موعد مؤكد يتداخل مع موعد يبدأ عند $start ومدته $slotMinutes؟
     * المؤكد يمنع غيره بنفس الوقت؛ المعلّق لا يحجز الوقت.
     */
    public static function confirmedOverlap(int $placeId, CarbonInterface $start, int $slotMinutes, ?int $exceptId = null): bool
    {
        $query = self::where('place_id', $placeId)
            ->where('status', self::STATUS_CONFIRMED)
            ->where('scheduled_at', '>', $start->copy()->subMinutes($slotMinutes))
            ->where('scheduled_at', '<', $start->copy()->addMinutes($slotMinutes));

        if ($exceptId !== null) {
            $query->whereKeyNot($exceptId);
        }

        return $query->exists();
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
}