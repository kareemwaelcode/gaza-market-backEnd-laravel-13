<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** مشترك في مساحة عمل، يديره صاحب المحل من اللوحة (إضافة، تعديل، إيقاف، تجديد، حذف). */
class DashboardSubscriber extends Model
{
    public const DURATION_DAY = 'day';

    public const DURATION_WEEK = 'week';

    public const DURATION_MONTH = 'month';

    public const DURATIONS = [self::DURATION_DAY, self::DURATION_WEEK, self::DURATION_MONTH];

    /** مفاتيح الدول التي يعرضها الفرونت (فلسطين وإسرائيل 48). */
    public const COUNTRY_CODES = ['970', '972'];

    protected $table = 'dashboard_subscribers';

    /** place_id و is_stopped لا يدخلان هنا عمداً: الأول من الجلسة، والثاني عبر مسار الإيقاف فقط. */
    protected $fillable = ['name', 'country_code', 'whatsapp', 'duration', 'amount', 'start_date', 'note'];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'amount' => 'decimal:2',
            'start_date' => 'date:Y-m-d',
            'is_stopped' => 'boolean',
        ];
    }

    /** المشتركون لأنواع نشاط محددة فقط (config/dashboard_plan.php). */
    public static function supportsPlace(Place $place): bool
    {
        return in_array($place->place_type, config('dashboard_plan.subscribers.place_types'), true);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
}