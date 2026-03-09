<?php

namespace App\Domain;

use DomainException;

final class Money
{
    public function __construct(
        public string $amount,
        public string $currency
    ) {}

    public static function of(string $amount, string $currency): self
    {
        if(bccomp($amount, '0',2) <= 0) {
            throw new DomainException('Amount must be > 0');
        }
        return new self($amount, $currency);
    }
}
