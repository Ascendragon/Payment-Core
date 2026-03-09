<?php
namespace App\Http\Request;

use Symfony\Component\Validator\Constraints as Assert;


final class TransferRequest
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Uuid]
        public readonly string $fromAccountId,

        #[Assert\NotBlank]
        #[Assert\Uuid]
        public readonly string $toAccountId,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern: '/^\d+(\.\d{1,2})?$/', message: 'Amount must be like 10.50')]
        public readonly string $amount,

        #[Assert\NotBlank]
        #[Assert\Regex(pattern:'/^[A-Z]{3}$/', message: 'Currency must be ISO-4217 like "RUB"')]
        public readonly string $currency,)
    {}
}
