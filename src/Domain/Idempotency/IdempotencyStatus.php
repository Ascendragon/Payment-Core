<?php

namespace App\Domain\Idempotency;

enum IdempotencyStatus: string
{
    case Processing = 'Processing';
    case Failed = 'Failed';
    case Succeeded = 'Succeeded';
}
