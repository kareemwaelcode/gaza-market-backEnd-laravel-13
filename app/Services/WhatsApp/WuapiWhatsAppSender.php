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
    ) {}

    public function send(string $to, string $text, ?string $idempotencyKey = null): void
    {
        if (blank($this->baseUrl) || blank($this->apiKey) || blank($this->accountId)) {
            throw WhatsAppSendException::permanent('wuapi is not configured (base url, api key or account id missing).');
        }

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
            throw WhatsAppSendException::retryable('wuapi connection failed.');
        }

        if ($response->successful()) {
            return;
        }

        $status = $response->status();

        // لا نضمّن جسم الرد في الرسالة: قد يحتوي بيانات المستلم.
        $message = "wuapi responded with HTTP {$status}.";

        if ($status === 429 || $status >= 500) {
            throw WhatsAppSendException::retryable($message);
        }

        throw WhatsAppSendException::permanent($message);
    }
}