<?php

namespace App\Entity;

use App\Repository\PaymentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: PaymentRepository::class)]
class Payment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid')]
    private ?Uuid $fromAccountId = null;

    #[ORM\Column(type: 'uuid')]
    private ?Uuid $toAccountId = null;

    #[ORM\Column(length: 25)]
    private ?string $amount = null;

    #[ORM\Column(length: 25)]
    private ?string $status = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromAccountId(): ?Uuid
    {
        return $this->fromAccountId;
    }

    public function setFromAccountId(Uuid $fromAccountId): static
    {
        $this->fromAccountId = $fromAccountId;

        return $this;
    }

    public function getToAccountId(): ?Uuid
    {
        return $this->toAccountId;
    }

    public function setToAccountId(Uuid $toAccountId): static
    {
        $this->toAccountId = $toAccountId;

        return $this;
    }

    public function getAmount(): ?string
    {
        return $this->amount;
    }

    public function setAmount(string $amount): static
    {
        $this->amount = $amount;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }
}
