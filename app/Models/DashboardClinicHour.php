<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** دوام يوم واحد لعيادة. غياب الصفوف يعني الدوام الافتراضي (config/dashboard_plan.php). */
class DashboardClinicHour extends Model
{
/** ترتيب الأسبوع عندنا يبدأ سبت (مطابق لـ DAYS بالفرونت). */
public const DAYS = ['sat', 'sun', 'mon', 'tue', 'wed', 'thu', 'fri'];

/** Carbon::dayOfWeek (أحد = 0) إلى مفتاح اليوم. */
private const BY_CARBON_DAY = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];

protected $table = 'dashboard_clinic_hours';

/** place_id لا يدخل هنا عمداً: يُحدد من الجلسة فقط. */
protected $fillable = ['day_of_week', 'is_open', 'open_time', 'close_time', 'slot_minutes'];

protected function casts(): array
{
return [
    'place_id' => 'integer',
    'is_open' => 'boolean',
    'slot_minutes' => 'integer',
];
}

public static function dayKey(int $carbonDayOfWeek): string
{
return self::BY_CARBON_DAY[$carbonDayOfWeek];
}

/**
 * دوام العيادة كاملاً: ['slot' => int, 'days' => [key => ['open' => bool, 'from' => 'H:i', 'to' => 'H:i']]]
 * مع الافتراضي للأيام غير المحفوظة.
 *
 * @return array{slot: int, days: array<string, array{open: bool, from: string, to: string}>}
 */
public static function scheduleFor(int $placeId): array
{
$defaults = config('dashboard_plan.appointments.default_hours');
$rows = self::where('place_id', $placeId)->get()->keyBy('day_of_week');

$days = [];

foreach (self::DAYS as $key) {
    $row = $rows->get($key);

    $days[$key] = $row === null
        ? [
            'open' => ! in_array($key, $defaults['closed_days'], true),
            'from' => $defaults['from'],
            'to' => $defaults['to'],
        ]
        : [
            'open' => (bool) $row->is_open,
            'from' => substr((string) $row->open_time, 0, 5),
            'to' => substr((string) $row->close_time, 0, 5),
        ];
}

$slot = $rows->first()?->slot_minutes ?? (int) config('dashboard_plan.appointments.default_slot');

return ['slot' => (int) $slot, 'days' => $days];
}

public function place(): BelongsTo
{
return $this->belongsTo(Place::class, 'place_id');
}
}