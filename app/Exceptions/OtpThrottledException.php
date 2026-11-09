<?php

namespace App\Exceptions;

use RuntimeException;

/** طلب كود جديد قبل انتهاء الفاصل أو بعد تجاوز السقف اليومي. */
class OtpThrottledException extends RuntimeException
{
    public function __construct(public readonly int $retryAfter, public readonly string $reason)
    {
        parent::__construct("OTP request throttled ({$reason}).");
    }
}