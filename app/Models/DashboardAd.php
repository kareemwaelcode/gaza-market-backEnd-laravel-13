<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** إعلان أو عرض ينشره صاحب المحل من لوحته (يمكن إخفاؤه بدل حذفه). */
class DashboardAd extends Model
{
    public const TYPE_ACTIVITY = 'activity';

    public const TYPE_WORKSHOP = 'workshop';

    public const TYPE_JOB = 'job';

    public const TYPE_OFFER = 'offer';

    /** مطابقة لـ AD_TYPE_LABELS بالفرونت (js/dashboard/pages/ads.js). المسموح لكل نشاط بـ config/dashboard_plan.php. */
    public const TYPES = [self::TYPE_ACTIVITY, self::TYPE_WORKSHOP, self::TYPE_JOB, self::TYPE_OFFER];

    protected $table = 'dashboard_ads';

    /** place_id لا يدخل هنا عمداً: يُحدد من الجلسة فقط. */
    protected $fillable = [
        'type', 'title', 'details', 'date', 'link', 'price_before', 'price_after', 'is_hidden',
    ];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'date' => 'date:Y-m-d',
            'price_before' => 'decimal:2',
            'price_after' => 'decimal:2',
            'is_hidden' => 'boolean',
        ];
    }

    /** تصنيف المتجر؛ المحل بلا تصنيف يُعامل كـ general. يتطلب تحميل علاقة storeCategory. */
    private static function variantOf(Place $place): string
    {
        return $place->storeCategory?->store_category_variant_key ?? 'general';
    }

    /** المتجر العام (بلا تصنيف خاص) لا خيارات إعلان خاصة له بالفرونت. */
    private static function isSpecificStore(Place $place): bool
    {
        return $place->place_type === 'store' && self::variantOf($place) !== 'general';
    }

    /** @return list<string> */
    public static function allowedTypes(Place $place): array
    {
        if ($place->place_type === 'store' && ! self::isSpecificStore($place)) {
            return config('dashboard_plan.ads.store_general_types');
        }

        return config('dashboard_plan.ads.types_by_place.'.$place->place_type, []);
    }

    /** أسعار قبل/بعد: لإعلان offer بمتجر له تصنيف خاص فقط (adOptions.offerPrices). */
    public static function allowsPrices(Place $place, string $type): bool
    {
        return $type === self::TYPE_OFFER && self::isSpecificStore($place);
    }

    /** الرابط لإعلان job فقط بالمطعم والمتجر الخاص (adOptions.linkOnlyForJob). */
    public static function allowsLink(Place $place, string $type): bool
    {
        $linkOnlyForJob = $place->place_type === 'restaurant' || self::isSpecificStore($place);

        return ! $linkOnlyForJob || $type === self::TYPE_JOB;
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
}