<?php

namespace App\Services;

use App\Exceptions\OtpThrottledException;
use App\Jobs\SendOtpMessage;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Facades\Cache;


class OtpService
{

    // @param  array<string, mixed>  $meta 
    // @throws OtpThrottledException

    public function request(string $phone, string $purpose, bool $eligible, array $meta = []): void
    {
    $this->withLock($phone, function () use ($phone, $purpose, $eligible, $meta) {
        $this->enforceLimits($phone, $purpose);

        if (! $eligible) {
            return;
        }

        $code = $this->generateCode();
        $issuedAt = now()->timestamp;

        Cache::put($this->codeKey($phone, $purpose), [
            'hash' => $this->hash($phone, $purpose, $code),
            'attempts' => 0,
            'expires_at' => $issuedAt + (int) config('otp.ttl'),
            'meta' => $meta,
        ], (int) config('otp.ttl'));

        SendOtpMessage::dispatch($phone, $purpose, $code, $issuedAt);
    });
    }

   
    // @return array<string, mixed>|null

    public function verify(string $phone, string $purpose, string $code): ?array
    {
    return $this->withLock($phone, function () use ($phone, $purpose, $code) {
        $key = $this->codeKey($phone, $purpose);
        $entry = Cache::get($key);

        if (! is_array($entry) || $entry['expires_at'] <= now()->timestamp) {
            Cache::forget($key);

            return null;
        }

        if (hash_equals($entry['hash'], $this->hash($phone, $purpose, $code))) {
            Cache::forget($key);

            return (array) $entry['meta'];
        }

        $entry['attempts']++;

        if ($entry['attempts'] >= (int) config('otp.max_attempts')) {
            Cache::forget($key);
        } else {
            Cache::put($key, $entry, max(1, $entry['expires_at'] - now()->timestamp));
        }

            return null;
        });
    }

    /** @throws OtpThrottledException */
    private function enforceLimits(string $phone, string $purpose): void
    {
        $cooldownKey = $this->key('cd', $phone, $purpose);

        if (Cache::has($cooldownKey)) {
            throw new OtpThrottledException($this->cooldownRemaining($cooldownKey), 'cooldown');
        }

        $dailyKey = $this->key('day', $phone, now()->format('Y-m-d'));
        $sentToday = (int) Cache::get($dailyKey, 0);

        if ($sentToday >= (int) config('otp.daily_limit')) {
            throw new OtpThrottledException(
                max(1, (int) now()->diffInSeconds(now()->endOfDay(), true)),
                'daily_limit'
            );
        }

        $cooldown = (int) config('otp.resend_cooldown');
        Cache::put($cooldownKey, now()->timestamp + $cooldown, $cooldown);
        Cache::put($dailyKey, $sentToday + 1, now()->addDays(2));
    }

    private function cooldownRemaining(string $cooldownKey): int
    {
        return max(1, (int) Cache::get($cooldownKey, 0) - now()->timestamp);
    }

    private function generateCode(): string
    {
        $fixed = config('otp.fixed_code');

        // الكود الثابت للتطوير فقط، ولا يعمل خارج local و testing مهما كانت قيمة .env.
        if (filled($fixed) && app()->environment(['local', 'testing'])) {
            return (string) $fixed;
        }

        $length = (int) config('otp.length');

        return str_pad((string) random_int(0, (10 ** $length) - 1), $length, '0', STR_PAD_LEFT);
    }

    private function hash(string $phone, string $purpose, string $code): string
    {
        return hash_hmac('sha256', "{$purpose}|{$phone}|{$code}", (string) config('app.key'));
    }

    private function codeKey(string $phone, string $purpose): string
    {
        return $this->key('code', $phone, $purpose);
    }

    private function key(string $type, string $phone, string $suffix): string
    {
        return "otp:{$type}:".hash('sha256', $phone).":{$suffix}";
    }

    /**
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     *
     * @throws OtpThrottledException
     */
    private function withLock(string $phone, callable $callback): mixed
    {
        try {
            return Cache::lock('otp:lock:'.hash('sha256', $phone), 5)->block(3, $callback);
        } catch (LockTimeoutException) {
            throw new OtpThrottledException(1, 'busy');
        }
    }
}