<?php

namespace App\Services\WhatsApp;

interface WhatsAppSender
{
    /**
     * @param  string  $to  رقم بصيغة E.164 مثل +970591234567
     * @param  string|null  $idempotencyKey  يجعل إعادة المحاولة آمنة (لا تُرسل الرسالة مرتين)
     *
     * @throws WhatsAppSendException
     */
    public function send(string $to, string $text, ?string $idempotencyKey = null): void;
}