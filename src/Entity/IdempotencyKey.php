<?php

namespace App\Entity;

use App\Repository\IdempotencyKeyRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: IdempotencyKeyRepository::class)]
class IdempotencyKey
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'uuid')]
    private ?Uuid $accountId = null;

    #[ORM\Column(length: 255)]
    private ?string $key = null;

    #[ORM\Column(length: 255)]
    private ?string $requestHash = null;

    #[ORM\Column(type: 'uuid')]
    private ?Uuid $paymentId = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAccountId(): ?Uuid
    {
        return $this->accountId;
    }

    public function setAccountId(Uuid $accountId): static
    {
        $this->accountId = $accountId;

        return $this;
    }

    public function getKey(): ?string
    {
        return $this->key;
    }

    public function setKey(string $key): static
    {
        $this->key = $key;

        return $this;
    }

    public function getRequestHash(): ?string
    {
        return $this->requestHash;
    }

    public function setRequestHash(string $requestHash): static
    {
        $this->requestHash = $requestHash;

        return $this;
    }

    public function getPaymentId(): ?Uuid
    {
        return $this->paymentId;
    }

    public function setPaymentId(Uuid $paymentId): static
    {
        $this->paymentId = $paymentId;

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
