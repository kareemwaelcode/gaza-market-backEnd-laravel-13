<?php

namespace App\Services\WhatsApp;

class WhatsAppCircuitOpenException extends WhatsAppSendException
{
    public function __construct(public readonly int $retryAfter)
    {
        parent::__construct('whatsapp circuit is open.', true);
    }
}