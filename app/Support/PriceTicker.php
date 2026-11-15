<?php

namespace App\Support;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Throwable;

/**
 * شريط الأسعار في الهيرو: اسم المنتج وسعره فقط.
 *
 * الفكرة: الزائر بيقرأ من الكاش بس. بناء الشريط (استعلام الداتابيز) بيصير من:
 *  - الأمر المجدول prices:refresh-ticker (كل N دقيقة + إعادة بناء ليلية)
 *  - إجراءات الإشراف (اعتماد/رفض/حذف/إخفاء) عبر refreshAfterChange()
 *  - أول طلب بعد كاش فاضي، مرة وحدة بس تحت قفل (احتياط لو الجدولة واقفة)
 * إضافة سعر جديد من مستخدم ما بتمسح الكاش؛ بيظهر مع التحديث الجاي.
 */
class PriceTicker
{
    // v2: الحقول تغيّرت (اسم + سعر فقط)، فما نقرأ نسخة v1 القديمة من الكاش
    public const KEY = 'prices:ticker:v2';

    private const LOCK_KEY = 'prices:ticker:v2:lock';

    // أقصى مدة بيمسك فيها القفل، وأقصى انتظار لزائر تاني بينتظر البناء
    private const LOCK_SECONDS = 15;

    private const LOCK_WAIT_SECONDS = 5;

    public static function items(): array
    {
        if (self::ttl() <= 0) {
            return self::query();
        }

        $cached = ApiCache::store()->get(self::KEY);

        if (is_array($cached)) {
            return $cached;
        }

        return self::rebuildOnce();
    }

    /**
     * يبني الشريط من الداتابيز ويخزّنه (إلا لو الكاش مطفي بـ ttl=0).
     */
    public static function refresh(): array
    {
        $items = self::query();

        if (self::ttl() > 0) {
            ApiCache::store()->put(self::KEY, $items, self::ttl());
        }

        return $items;
    }

    /**
     * للاستدعاء بعد تغيير إشرافي ناجح (بعد الـ commit). ما بنخلي فشل الكاش يكسر
     * الاستجابة والتغيير تم أصلاً؛ بنسجّل الخطأ وبنمسح المفتاح ليتبني عند أول قراءة.
     */
    public static function refreshAfterChange(): void
    {
        try {
            self::refresh();
        } catch (Throwable $e) {
            report($e);

            try {
                self::forget();
            } catch (Throwable) {
                // الجدولة هي شبكة الأمان الأخيرة
            }
        }
    }

    public static function forget(): void
    {
        ApiCache::store()->forget(self::KEY);
    }

    private static function ttl(): int
    {
        return (int) config('prices.ticker.ttl');
    }

    // زائر واحد بس بيبني، والباقي بينتظروا ويقرأوا النتيجة (بدل ما كلهم يضربوا الداتابيز)
    private static function rebuildOnce(): array
    {
        try {
            return ApiCache::store()->lock(self::LOCK_KEY, self::LOCK_SECONDS)
                ->block(self::LOCK_WAIT_SECONDS, function () {
                    $cached = ApiCache::store()->get(self::KEY);

                    return is_array($cached) ? $cached : self::refresh();
                });
        } catch (LockTimeoutException) {
            return self::query();
        }
    }

    private static function query(): array
    {
        // آخر سعر معتمد لكل منتج فعّال؛ إذا انحذف/انخفى الأحدث بيرجع السابق تلقائياً
        $latestIds = ProductPrice::query()
            ->approved()
            ->whereIn('product_id', Product::query()->active()->select('id'))
            ->selectRaw('max(id)')
            ->groupBy('product_id');

        return ProductPrice::query()
            ->select(['id', 'product_id', 'product_price_value'])
            ->whereIn('id', $latestIds)
            ->with('product:id,product_name_ar,product_name_en')
            ->orderByDesc('id')
            ->limit(max(1, (int) config('prices.ticker.limit')))
            ->get()
            ->map(fn (ProductPrice $price) => [
                'product_id' => $price->product_id,
                'product_name_ar' => $price->product?->product_name_ar,
                'product_name_en' => $price->product?->product_name_en,
                'price' => $price->product_price_value,
            ])
            ->values()
            ->all();
    }
}