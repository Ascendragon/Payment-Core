<?php

namespace App\Domain;

use App\Domain\Transfer\Exception\CurrencyMismatchException;
use DomainException;

final class Money
{
    public function __construct(
        public readonly string $amount,
        public readonly string $currency
    ) {}

    public static function of(string $amount, string $currency): self
    {
        if(bccomp($amount, '0',2) <= 0) {
            throw new DomainException('Amount must be > 0');
        }
        return new self($amount, $currency);
    }
    public function isGreaterOrEqual(Money $other): bool
    {
        if($this->currency !== $other->currency) {
            throw new CurrencyMismatchException();
        }
        return bccomp($this->amount, $other->amount, 2) >= 0;

    }
    public function subtract(Money $other): self
    {
        if($this->currency !== $other->currency) {
            throw new CurrencyMismatchException();
        }
        $currentBalance = bcsub($this->amount, $other->amount, 2);
        return new self($currentBalance, $other->currency);
    }
}
