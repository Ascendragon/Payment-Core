<?php

namespace App\Domain\Transfer\Message;

class TransferCompletedMessage
{
    public function __construct
    (
        public readonly string $eventId,
        public readonly string $fromAccountId,
        public readonly string $date,
        public readonly string $toAccountId,
        public readonly string $currency,
        public readonly string $amount
    ) {}

}
