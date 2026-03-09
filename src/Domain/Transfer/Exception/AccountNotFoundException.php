<?php

namespace App\Domain\Transfer\Exception;

use App\Domain\DomainException;

class AccountNotFoundException extends DomainException
{
    public function __construct( string $accountId)
    {
        $message = sprintf('Счет с ID "%s" не найден.', $accountId);
        parent::__construct($message);
    }
}
