<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppSender;
use App\Services\WhatsApp\WhatsAppSendException;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendOtpMessage implements ShouldBeEncrypted, ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [5, 15];

    public function __construct(
        public string $phone,
        public string $purpose,
        public string $code,
        public int $issuedAt,
    ) {}

    public function handle(WhatsAppSender $sender): void
    {
        if (now()->timestamp - $this->issuedAt >= (int) config('otp.ttl')) {
            return;
        }

        // الأرقام تُتحقق بالـ regex (05[69]) قبل الوصول هنا، والتحويل الدولي نفسه المستخدم برسائل القبول.
        $to = SendPlaceStatusMessage::toE164($this->phone);

        if ($to === null) {
            Log::warning('OTP message skipped: invalid phone number.', ['purpose' => $this->purpose]);

            return;
        }

        try {
            $sender->send($to, $this->message(), $this->idempotencyKey());
        } catch (WhatsAppSendException $e) {
            if ($e->retryable) {
                throw $e;
            }

            Log::error('OTP message failed permanently.', [
                'purpose' => $this->purpose,
                'error' => $e->getMessage(),
            ]);

            $this->fail($e);
        }
    }

private function message(): string
{
    $minutes = max(1, intdiv((int) config('otp.ttl'), 60));

    $intro = $this->purpose === 'dashboard'
        ? '🔐 كود دخول لوحة التحكم في غزة ماركت'
        : '🎉 كود التفعيل الخاص بك في غزة ماركت';

    return "👋 مرحباً بك في غزة ماركت!\n\n"
        ."{$intro}:\n"
        ."🔑 {$this->code}\n\n"
        ."⏳ صالح لمدة {$minutes} دقائق.\n"
        ."⚠️ لا تشارك هذا الرمز مع أي شخص.";
}

    private function idempotencyKey(): string
    {
        return 'otp-'.substr(hash('sha256', $this->phone), 0, 16)."-{$this->purpose}-{$this->issuedAt}";
    }
}