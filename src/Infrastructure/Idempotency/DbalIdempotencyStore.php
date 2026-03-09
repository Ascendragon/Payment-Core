<?php

namespace App\Infrastructure\Idempotency;

use App\Domain\Idempotency\IdempotencyStatus;
use App\Domain\IdempotencyKey;
use App\Domain\Transfer\Exception\AlreadyProcessedException;
use App\Domain\Transfer\Exception\AlreadyProcessingException;
use App\Domain\Transfer\Exception\IdempotencyConflictException;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Component\Uid\Uuid;

class DbalIdempotencyStore
{
    public function __construct(public Connection $db) {}

    public function acquire(Uuid $accountId, IdempotencyKey $idempotencyKey,string $requestHash): void
    {
        $affectedRows = $this->db->executeStatement(
            "INSERT INTO idempotency_key(account_id, key, request_hash, status)
             VALUES (:account_id, :key, :request_hash, :status)
             ON CONFLICT (key) DO NOTHING",
            [
                'account_id' => $accountId,
                'key' => $idempotencyKey->value,
                'request_hash' => $requestHash,
                'status' => IdempotencyStatus::Processing->value
            ]
        );

        if ($affectedRows === 1) {
            return;
        }


        $existing = $this->db->fetchAssociative("
            SELECT request_hash, status
            FROM idempotency_key
            WHERE key = :key FOR UPDATE",
            [
                'key' => $idempotencyKey->value
            ]
        );

        if($existing['request_hash'] !== $requestHash) {
            throw new IdempotencyConflictException();
        }

        if($existing['status'] === IdempotencyStatus::Processing->value) {
            throw new AlreadyProcessingException();
        }

        throw new AlreadyProcessedException();
    }

    public function markAsCompleted(Uuid $accountId, IdempotencyKey $idempotencyKey): void
    {
        $this->db->executeStatement(
            "UPDATE idempotency_key SET status = :status WHERE account_id = :account_id AND key = :key",
            [
                'status' => IdempotencyStatus::Succeeded->value,
                'account_id' => $accountId->toRfc4122(),
                'key' => $idempotencyKey->value
            ]
        );
    }
}
