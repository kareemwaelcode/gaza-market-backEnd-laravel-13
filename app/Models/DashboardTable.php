<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/** طاولة في كافيه أو مطعم-كافيه، يديرها صاحب المحل ويُحجز عليها. */
class DashboardTable extends Model
{
    use SoftDeletes;

    public const STATUS_AVAILABLE = 'available';

    public const STATUS_OCCUPIED = 'occupied';

    public const STATUSES = [self::STATUS_AVAILABLE, self::STATUS_OCCUPIED];

    protected $table = 'dashboard_tables';

    /** place_id لا يدخل هنا عمداً: يُحدد من الجلسة فقط. */
    protected $fillable = ['name', 'seats', 'status'];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'seats' => 'integer',
        ];
    }

    /** الطاولات والحجز متاحة لأنواع نشاط محددة فقط (config/dashboard_plan.php). */
    public static function supportsPlace(Place $place): bool
    {
        return in_array($place->place_type, config('dashboard_plan.tables.place_types'), true);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
}