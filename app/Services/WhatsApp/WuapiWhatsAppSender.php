<?php

namespace App\Services\WhatsApp;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

class WuapiWhatsAppSender implements WhatsAppSender
{
    public function __construct(
        private readonly ?string $baseUrl,
        private readonly ?string $apiKey,
        private readonly ?string $accountId,
        private readonly int $timeout = 10,
        private readonly ?WhatsAppSendGuard $guard = null,
        private readonly ?WhatsAppCircuitBreaker $breaker = null,
    ) {}

    public function send(string $to, string $text, ?string $idempotencyKey = null): void
    {
        if (blank($this->baseUrl) || blank($this->apiKey) || blank($this->accountId)) {
            throw WhatsAppSendException::permanent('wuapi is not configured (base url, api key or account id missing).');
        }

        $this->breaker?->ensureAvailable();

        // السقف اليومي وقائمة الأرقام المسموحة: قبل أي اتصال بالمزوّد.
        $this->guard?->acquire($to);

        $request = Http::baseUrl($this->baseUrl)
            ->withToken($this->apiKey)
            ->acceptJson()
            ->asJson()
            ->timeout($this->timeout);

        if ($idempotencyKey !== null) {
            $request = $request->withHeaders(['Idempotency-Key' => $idempotencyKey]);
        }

        try {
            $response = $request->post('/v1/messages', [
                'accountId' => $this->accountId,
                'to' => $to,
                'text' => $text,
            ]);
        } catch (ConnectionException) {
            $this->guard?->release();
            $this->breaker?->recordFailure();

            throw WhatsAppSendException::retryable('wuapi connection failed.');
        }

        if ($response->successful()) {
            $this->breaker?->recordSuccess();

            return;
        }

        // الإرسال الفاشل ما يُحتسب من السقف اليومي.
        $this->guard?->release();

        $status = $response->status();

        // لا نضمّن جسم الرد في الرسالة: قد يحتوي بيانات المستلم.
        $message = "wuapi responded with HTTP {$status}.";

        if ($status === 429 || $status >= 500) {
            $this->breaker?->recordFailure();

            throw WhatsAppSendException::retryable($message);
        }

        $this->breaker?->recordSuccess();

        throw WhatsAppSendException::permanent($message);
    }
}