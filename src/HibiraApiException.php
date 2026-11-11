<?php

namespace Hibira\Pulse;

use RuntimeException;

class HibiraApiException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 0, public readonly ?string $reason = null)
    {
        parent::__construct($message, $status);
    }
}
