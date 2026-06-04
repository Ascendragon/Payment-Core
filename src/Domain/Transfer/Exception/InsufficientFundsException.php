<?php

namespace App\Domain\Transfer\Exception;

use App\Domain\DomainException;

class InsufficientFundsException extends DomainException
{
    public function __construct(string $message = 'Insufficient funds for transfer.')
    {
        parent::__construct($message);
    }
}
