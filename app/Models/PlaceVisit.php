<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class PlaceVisit extends Model
{
    /** "اليوم" يُحسب بتوقيت غزة وليس UTC، حتى لا ينقلب اليوم الساعة 2-3 فجراً. */
    public const TIMEZONE = 'Asia/Gaza';

    public const WEEK_DAYS = 7;

    protected $table = 'place_visits';

    public $timestamps = false;

    protected $fillable = ['place_id', 'visit_date', 'visits_count'];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'visits_count' => 'integer',
        ];
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }

    public static function today(): string
    {
        return now(self::TIMEZONE)->toDateString();
    }

    /** أول يوم ضمن "الأسبوع" (آخر 7 أيام بما فيها اليوم). */
    public static function weekStart(): string
    {
        return now(self::TIMEZONE)->subDays(self::WEEK_DAYS - 1)->toDateString();
    }

    /** زيادة ذرية: ينشئ صف اليوم بقيمة 1، أو يزيد الموجود بـ +1 بعملية واحدة (آمنة مع الطلبات المتزامنة). */
    public static function record(int $placeId): void
    {
        static::upsert(
            [['place_id' => $placeId, 'visit_date' => self::today(), 'visits_count' => 1]],
            ['place_id', 'visit_date'],
            ['visits_count' => DB::raw('visits_count + 1')],
        );
    }

    /** @return array{today: int, week: int, total: int} */
    public static function summary(int $placeId): array
    {
        $row = static::query()
            ->where('place_id', $placeId)
            ->selectRaw(
                'COALESCE(SUM(visits_count), 0) as total, '
                .'COALESCE(SUM(CASE WHEN visit_date = ? THEN visits_count ELSE 0 END), 0) as today, '
                .'COALESCE(SUM(CASE WHEN visit_date >= ? THEN visits_count ELSE 0 END), 0) as week',
                [self::today(), self::weekStart()]
            )
            ->first();

        return [
            'today' => (int) $row->today,
            'week' => (int) $row->week,
            'total' => (int) $row->total,
        ];
    }
}