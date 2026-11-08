<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Log;

class LogWhatsAppSender implements WhatsAppSender
{
    public function send(string $to, string $text, ?string $idempotencyKey = null): void
    {
        // لا نسجّل نص الرسالة ولا الرقم كاملاً: الرسائل قد تحمل أكواد تحقق لاحقاً.
        Log::info('WhatsApp message not sent (log driver).', [
            'to' => substr($to, 0, 6).str_repeat('*', max(strlen($to) - 6, 0)),
            'length' => mb_strlen($text),
            'idempotency_key' => $idempotencyKey,
        ]);
    }
}