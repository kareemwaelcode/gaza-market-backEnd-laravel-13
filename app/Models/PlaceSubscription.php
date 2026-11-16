<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

/** دفعة باقة مدفوعة لمحل. الحالة الحالية للمحل تُقرأ من places، وهذا الجدول سجل الدفعات. */
class PlaceSubscription extends Model
{
    protected $table = 'place_subscriptions';

    /** كل الحقول تُكتب من grantTo() فقط، فلا شيء يدخل من الطلب. */
    protected $guarded = ['*'];

    protected function casts(): array
    {
        return [
            'place_id' => 'integer',
            'subscription_amount' => 'decimal:2',
            'subscription_period_months' => 'integer',
            'subscription_starts_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'subscription_reminder_sent_at' => 'datetime',
            'subscription_cancelled_at' => 'datetime',
        ];
    }

    /**
     * يفعّل الباقة أو يجددها بضغطة واحدة. تبدأ الفترة من الآن، وإذا كان المحل مشتركاً
     * وباقته لم تنتهِ فتبدأ من نهايتها (فلا يخسر الأيام الباقية).
     * السعر والمدة من config/subscription.php وتُنسخ إلى الصف.
     */
    public static function grantTo(Place $place, ?int $adminId = null, ?string $notes = null): self
    {
        return DB::transaction(function () use ($place, $adminId, $notes) {
            // قفل صف المحل يسلسل ضغطتين متزامنتين: الثانية تبدأ من نهاية الأولى.
            $locked = Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

            $now = now();
            $current = $locked->place_plan_expires_at;
            $starts = ($locked->place_plan === 'paid' && $current !== null && $current->isFuture())
                ? $current->copy()
                : $now->copy();

            $months = (int) config('subscription.period_months');

            $subscription = new self;
            $subscription->forceFill([
                'place_id' => $locked->id,
                'subscription_amount' => config('subscription.price'),
                'subscription_currency' => config('subscription.currency'),
                'subscription_period_months' => $months,
                'subscription_starts_at' => $starts,
                'subscription_ends_at' => $starts->copy()->addMonthsNoOverflow($months),
                'subscription_notes' => $notes,
                'subscription_activated_by' => $adminId,
            ])->save();

            $locked->forceFill([
                'place_plan' => 'paid',
                'place_plan_expires_at' => $subscription->subscription_ends_at,
            ])->save();

            $place->setRawAttributes($locked->getAttributes(), true);

            return $subscription;
        }, 3);
    }

    /** يلغي الباقة فوراً: كل اشتراك لم ينتهِ يُعلَّم ملغى، والمحل يرجع مجانياً. السجل يبقى. */
    public static function cancelFor(Place $place): int
    {
        return DB::transaction(function () use ($place) {
            $locked = Place::whereKey($place->id)->lockForUpdate()->firstOrFail();

            $cancelled = self::where('place_id', $locked->id)
                ->whereNull('subscription_cancelled_at')
                ->where('subscription_ends_at', '>', now())
                ->update(['subscription_cancelled_at' => now(), 'updated_at' => now()]);

            $locked->forceFill(['place_plan' => 'free', 'place_plan_expires_at' => null])->save();

            $place->setRawAttributes($locked->getAttributes(), true);

            return $cancelled;
        }, 3);
    }

    public function place(): BelongsTo
    {
        return $this->belongsTo(Place::class, 'place_id');
    }
}