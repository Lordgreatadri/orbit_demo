<?php

namespace App\Workforce;

use RuntimeException;

class AttendanceException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
