<?php

namespace App\Services\WhatsApp;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * قاطع دائرة لمزوّد الواتساب: لما المزوّد يقع (5xx أو 429 أو انقطاع اتصال) نوقف الإرسال مؤقتاً
 * بدل ما نضل نضرب عليه ونحرق المحاولات والـ workers.
 *
 * الحالات: مغلق ← (فشل >= threshold ضمن window ثانية) ← مفتوح لمدة open_seconds
 *          ← نصف مفتوح: محاولة وحدة (probe) فقط ← نجحت: مغلق، فشلت: مفتوح من جديد.
 *
 * الحالة بالكاش، فإذا الكاش وقع نسجّل الخطأ ونكمل الإرسال (القاطع ما لازم يكون نقطة فشل).
 * ملاحظة تشغيلية: مع كاش file/database الحالة لكل سيرفر لحاله؛ للمشاركة بين السيرفرات استخدم Redis.
 */
class WhatsAppCircuitBreaker
{
    private const PREFIX = 'whatsapp:circuit:';

    /** أطول انتظار نطلبه من الطابور لباقي الطلبات بينما المحاولة التجريبية شغّالة. */
    private const PROBE_WAIT_SECONDS = 5;

    public function __construct(
        private readonly int $threshold,
        private readonly int $window,
        private readonly int $openSeconds,
        private readonly ?string $store = null,
        private readonly bool $enabled = true,
    ) {}

    public static function fromConfig(): self
    {
        $store = config('whatsapp.circuit.store');

        return new self(
            (int) config('whatsapp.circuit.threshold', 5),
            (int) config('whatsapp.circuit.window', 60),
            (int) config('whatsapp.circuit.open_seconds', 120),
            is_string($store) && $store !== '' ? $store : null,
            (bool) config('whatsapp.circuit.enabled', true),
        );
    }

    /**
     * يُستدعى قبل أي اتصال بالمزوّد.
     *
     * @throws WhatsAppCircuitOpenException إذا الدائرة مفتوحة أو المحاولة التجريبية محجوزة لطلب ثاني.
     */
    public function ensureAvailable(): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $cache = $this->cache();
            $openUntil = $cache->get(self::PREFIX.'open_until');

            if ($openUntil === null) {
                return;
            }

            $remaining = (int) $openUntil - $this->now();

            if ($remaining > 0) {
                throw new WhatsAppCircuitOpenException($this->clampRetryAfter($remaining));
            }

            // انتهت فترة الفتح: نصف مفتوح. add ذرّية، فطلب واحد بس بياخذ الـ probe.
            if (! $cache->add(self::PREFIX.'probe', 1, $this->openSeconds)) {
                throw new WhatsAppCircuitOpenException(min($this->openSeconds, self::PROBE_WAIT_SECONDS));
            }
        } catch (WhatsAppCircuitOpenException $e) {
            throw $e;
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** فشل من جهة المزوّد (5xx أو 429 أو انقطاع اتصال). أخطاء 4xx ما بتنحسب. */
    public function recordFailure(): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $cache = $this->cache();

            // فشل المحاولة التجريبية: نفتح من جديد فوراً بدون انتظار العدّاد.
            if ($cache->has(self::PREFIX.'probe')) {
                $this->open($cache);

                return;
            }

            // عدّاد بنافذة ثابتة: ينتهي لحاله بعد window ثانية من أول فشل، فالفشل القديم بينتسى.
            $cache->add(self::PREFIX.'failures', 0, $this->window);
            $count = (int) $cache->increment(self::PREFIX.'failures');

            if ($count >= max(1, $this->threshold)) {
                $this->open($cache);
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    /** المزوّد ردّ بشكل سليم (أو رفض الطلب بخطأ عميل): نصفّر العدّاد، ونسكّر الدائرة إذا كانت نصف مفتوحة. */
    public function recordSuccess(): void
    {
        if (! $this->enabled) {
            return;
        }

        try {
            $cache = $this->cache();

            $cache->forget(self::PREFIX.'failures');

            if ($cache->has(self::PREFIX.'open_until')) {
                $cache->forget(self::PREFIX.'open_until');
                $cache->forget(self::PREFIX.'probe');
            }
        } catch (Throwable $e) {
            report($e);
        }
    }

    private function open(Repository $cache): void
    {
        // TTL أطول من فترة الفتح بكثير: لو ما إجا أي طلب بعدها، تبقى الدائرة نصف مفتوحة لا مغلقة بصمت.
        $cache->put(self::PREFIX.'open_until', $this->now() + $this->openSeconds, $this->openSeconds + 3600);
        $cache->forget(self::PREFIX.'failures');
        $cache->forget(self::PREFIX.'probe');
    }

    private function clampRetryAfter(int $seconds): int
    {
        return max(1, min($this->openSeconds, $seconds));
    }

    private function now(): int
    {
        return now()->getTimestamp();
    }

    private function cache(): Repository
    {
        return Cache::store($this->store);
    }
}