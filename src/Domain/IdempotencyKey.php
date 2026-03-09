<?php

namespace App\Domain;

class IdempotencyKey
{
    private function __construct(public readonly string $value) {}

    public static function fromHeader(?string $value): self
    {
        $value = (string) $value;

        if($value === '' || strlen($value) > 64) {
            throw new \DomainException('Missing or invalid idempotency key');
        }
        return new self($value);
    }

}
