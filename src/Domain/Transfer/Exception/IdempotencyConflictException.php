<?php

namespace App\Domain\Transfer\Exception;

use App\Domain\DomainException;

class IdempotencyConflictException extends DomainException
{
    public function __construct()
    {
        parent::__construct('Idempotency conflict. Request parameters changed.');
    }
}
