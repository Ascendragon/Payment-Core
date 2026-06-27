<?php

namespace App\Application\Transfer;

use App\Contracts\MessageBrokerInterface;
use App\Domain\IdempotencyKey;
use App\Domain\Money;
use App\Domain\Transfer\Exception\AccountNotFoundException;
use App\Domain\Transfer\Exception\ConcurrencyConflictException;
use App\Domain\Transfer\Exception\ForbiddenException;
use App\Domain\Transfer\Exception\InsufficientFundsException;
use App\Domain\Transfer\Message\TransferCompletedMessage;
use App\Infrastructure\Database\DatabaseRetryRunner;
use App\Infrastructure\Idempotency\DbalIdempotencyStore;
use App\Infrastructure\Outbox\DbalOutboxStore;
use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

final class TransferService
{
    public function __construct(
        private readonly Connection $db,
        private readonly DbalIdempotencyStore $store,
        private readonly MessageBrokerInterface $messageBroker,
        private readonly DbalOutboxStore $outboxStore,
        private readonly DatabaseRetryRunner $retryRunner,
    ) {
    }

    public function transfer(
        string $fromAccountId,
        string $toAccountId,
        string $amount,
        string $currency,
        string $idempotencyKey,
        string $callerId
    ): void {
        if ($fromAccountId === $toAccountId) {
            throw new \InvalidArgumentException('Transfer to the same account is not allowed.');
        }

        // Создаем объект ключа
        $key = IdempotencyKey::fromHeader($idempotencyKey);

        // Генерируем хэш запроса(чтобы избежать подмены данных)
        $requestHash = hash('sha256', $fromAccountId.$toAccountId.$amount.$currency);

        $amountToWithdraw = new Money($amount, $currency);

        $this->retryRunner->run(function () use ($fromAccountId, $toAccountId, $amount, $currency, $key, $amountToWithdraw, $requestHash,$callerId) {
            $this->store->acquire(Uuid::fromString($fromAccountId), $key, $requestHash);

            $senderData = $this->db->fetchAssociative('SELECT balance,version,currency,owner_id FROM account WHERE id = :id', ['id' => $fromAccountId]);

            if (false === $senderData) {
                throw new AccountNotFoundException($fromAccountId);
            }
            if($senderData['owner_id'] !== $callerId) {
                throw new ForbiddenException();
            }

            $receiverData = $this->db->fetchAssociative(
                'SELECT id, currency,version FROM account WHERE id = :id',
                ['id' => $toAccountId]
            );
            if (false === $receiverData) {
                throw new AccountNotFoundException($toAccountId);
            }

            if ($senderData['currency'] !== $currency) {
                throw new \InvalidArgumentException('Sender account currency does not match transfer currency.');
            }

            if ($receiverData['currency'] !== $currency) {
                throw new \InvalidArgumentException('Receiver account currency does not match transfer currency.');
            }

            $currentBalance = new Money((string) $senderData['balance'], $senderData['currency']);

            if (!$currentBalance->isGreaterOrEqual($amountToWithdraw)) {
                throw new InsufficientFundsException();
            }

            $newSenderBalance = $currentBalance->subtract($amountToWithdraw);

            $updatedSenderRows = $this->db->executeStatement('UPDATE account set balance = :new_balance,version = version + 1 WHERE id = :id AND version = :version', [
                'id' => $fromAccountId,
                'version' => $senderData['version'],
                'new_balance' => $newSenderBalance->amount,
            ]);

            if (0 === $updatedSenderRows) {
                throw new ConcurrencyConflictException('Sender account was modified concurrently.');
            }

            $updatedReceiverRows = $this->db->executeStatement(
                'UPDATE account SET balance = balance + CAST(:amount AS NUMERIC), version = version + 1 WHERE id = :id AND version = :version',
                [
                    'id' => $toAccountId,
                    'amount' => $amount,
                    'version' => $receiverData['version'],
                ]
            );
            if (0 === $updatedReceiverRows) {
                throw new ConcurrencyConflictException('Receiver account was modified concurrently.');
            }

            $message = new TransferCompletedMessage(
                Uuid::v4()->toRfc4122(), // или просто (string) Uuid::v4()
                $fromAccountId,
                date('Y-m-d H:i:s'),
                $toAccountId,
                $currency,
                $amount,
            );

            $this->outboxStore->save($message);
            $this->store->markAsCompleted(Uuid::fromString($fromAccountId), $key);
        });
    }
}
