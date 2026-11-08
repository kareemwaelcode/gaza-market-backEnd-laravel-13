<?php

namespace App\Services\WhatsApp;

use RuntimeException;

class WhatsAppSendException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable)
    {
        parent::__construct($message);
    }

    public static function retryable(string $message): self
    {
        return new self($message, true);
    }

    public static function permanent(string $message): self
    {
        return new self($message, false);
    }
}