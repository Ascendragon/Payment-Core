<?php

namespace App\Domain\Transfer\Exception;

use App\Domain\DomainException;

class InsufficientFundsException extends DomainException
{
    public function __construct()
    {
        parent::__construct("Insufficient funds for transfer");
    }
}
