<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Cache;

/**
 * يحمي باقة مزوّد الواتساب: سقف يومي لعدد الرسائل، وقائمة أرقام مسموحة.
 * يُستدعى acquire() قبل كل إرسال فعلي، وrelease() إذا فشل الإرسال قبل أن يُحتسب.
 */
class WhatsAppSendGuard
{
    /**
     * @param  int  $dailyLimit  0 = بلا سقف
     * @param  array<int, string>  $allowedTo  فارغة = الكل مسموح
     */
    public function __construct(
        private readonly int $dailyLimit = 0,
        private readonly array $allowedTo = [],
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            (int) config('whatsapp.daily_limit', 0),
            (array) config('whatsapp.allowed_to', []),
        );
    }

    /** @throws WhatsAppSendException */
    public function acquire(string $to): void
    {
        if ($this->allowedTo !== [] && ! $this->isAllowed($to)) {
            throw WhatsAppSendException::permanent('recipient is not in the allowed list.');
        }

        if ($this->dailyLimit <= 0) {
            return;
        }

        $key = $this->counterKey();
        Cache::add($key, 0, now()->addDays(2));

        if ((int) Cache::increment($key) > $this->dailyLimit) {
            Cache::decrement($key);

            throw WhatsAppSendException::permanent('daily whatsapp limit reached.');
        }
    }

    public function release(): void
    {
        if ($this->dailyLimit > 0) {
            Cache::decrement($this->counterKey());
        }
    }

    private function isAllowed(string $to): bool
    {
        $target = $this->normalize($to);

        foreach ($this->allowedTo as $allowed) {
            if ($this->normalize($allowed) === $target) {
                return true;
            }
        }

        return false;
    }

    /** أرقام فقط بصيغة دولية: 0591234567 و +970591234567 و 00970591234567 كلها نفس الرقم. */
    private function normalize(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (str_starts_with($digits, '00')) {
            return substr($digits, 2);
        }

        return str_starts_with($digits, '0') ? '970'.substr($digits, 1) : $digits;
    }

    private function counterKey(): string
    {
        return 'whatsapp:sent:'.now()->format('Y-m-d');
    }
}