<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** حجز طاولة في كافيه أو مطعم-كافيه، يديره صاحب المحل من اللوحة. */
class DashboardReservation extends Model
{
    use SoftDeletes;

    public const STATUS_PENDING = 'pending';

    public const STATUS_CONFIRMED = 'confirmed';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [self::STATUS_PENDING, self::STATUS_CONFIRMED, self::STATUS_CANCELLED];

    /** الانتقالات المسموحة (من → إلى) حسب عقد الحجوزات القسم 5.2. */
    public const TRANSITIONS = [
        self::STATUS_PENDING => [self::STATUS_CONFIRMED, self::STATUS_CANCELLED],
        self::STATUS_CONFIRMED => [self::STATUS_CANCELLED],
    ];

    protected $table = 'dashboard_reservations';

    /** place_id و dashboard_table_id و status لا تدخل هنا عمداً: تُحدد من الخادم فقط. */
    protected $fillable = ['name', 'country_code', 'whatsapp', 'reserved_at', 'people_count', 'note'];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'dashboard_table_id' => 'integer',
            'people_count' => 'integer',
            'reserved_at' => 'datetime',
        ];
    }

    public function canMoveTo(string $status): bool
    {
        return in_array($status, self::TRANSITIONS[$this->status] ?? [], true);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    /** مع المحذوفة ناعماً: الحجز القديم يعرض "طاولة محذوفة". */
    public function table(): BelongsTo
    {
        return $this->belongsTo(DashboardTable::class, 'dashboard_table_id')->withTrashed();
    }
}