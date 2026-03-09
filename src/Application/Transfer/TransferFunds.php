<?php

namespace App\Application\Transfer;

use App\Domain\IdempotencyKey;
use App\Domain\Money;
use Doctrine\DBAL\Connection;

class TransferFunds
{
    public function __construct(private readonly Connection $db) {}

    public function __invoke(
        IdempotencyKey $idempotencyKey,
        string $fromAccountId,
        string $toAccountId,
        Money $money) : array
    {
         if($fromAccountId !== $toAccountId) {
             throw new \DomainException('fromAccountId and toAccountId must be different');
         }
    }
}
