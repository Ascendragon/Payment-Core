<?php

namespace App\Domain\Idempotency;

enum IdempotencyStatus: string
{
    case Failed = 'Failed';
    case Succeeded = 'Succeeded';
}
