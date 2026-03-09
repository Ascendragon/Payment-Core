<?php

namespace App\Domain\Payment;

enum PaymentStatus: string
{
    case Succeeded = 'Succeeded';
    case Pending = 'Pending';
    case Failed = 'Failed';
}
