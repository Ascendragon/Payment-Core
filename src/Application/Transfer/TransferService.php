<?php

namespace App\Application\Transfer;

use App\Contracts\MessageBrokerInterface;
use App\Domain\IdempotencyKey;
use App\Domain\Transfer\Exception\AccountNotFoundException;
use App\Domain\Transfer\Exception\InsufficientFundsException;
use App\Domain\Transfer\Message\TransferCompletedMessage;
use App\Entity\Account;
use App\Infrastructure\Idempotency\DbalIdempotencyStore;
use App\Infrastructure\Outbox\DbalOutboxStore;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\OptimisticLockException;
use Symfony\Component\Uid\Uuid;

final class TransferService
{
    public function __construct
    (
        private readonly  Connection $db,
        private readonly DbalIdempotencyStore $store,
        private readonly MessageBrokerInterface $messageBroker,
        private readonly DbalOutboxStore $outboxStore,
    ) {}

    public function transfer(
        string $fromAccountId,
        string $toAccountId,
        string $amount,
        string $currency,
        string $idempotencyKey
    )
    {
        // Создаем объект ключа
        $key = IdempotencyKey::fromHeader($idempotencyKey);

        // Генерируем хэш запроса(чтобы избежать подмены данных)
        $requestHash = md5($fromAccountId . $toAccountId . $amount.$currency);

        // Попытка занять ключ


        // Начинаем транзакцию
        $this->db->beginTransaction();

        try {
            $this->store->acquire(Uuid::fromString($fromAccountId), $key, $requestHash);

            $senderData = $this->db->fetchAssociative("SELECT balance,version FROM account WHERE id = :id", ['id' => $fromAccountId]);

            if ($senderData === false) {
                throw new AccountNotFoundException($fromAccountId);

            }
            if(bccomp((string)$senderData['balance'], $amount , 2) === -1) {
                throw new InsufficientFundsException();
            }

            $res = $this->db->executeStatement(
                "UPDATE account SET balance = CAST((CAST(balance AS NUMERIC) - CAST(:amount AS NUMERIC)) AS VARCHAR), version = version + 1 WHERE id = :id AND version = :version",
                ['id' => $fromAccountId, 'version' => $senderData['version'], 'amount' => $amount]
            );
            if($res === 0) {
                throw new OptimisticLockException("В процессе выполнения возникла ошибка",Account::class );
            }

            $this->db->executeStatement(
                "UPDATE account SET balance = CAST((CAST(balance AS NUMERIC) + CAST(:amount AS NUMERIC)) AS VARCHAR), version = version + 1 WHERE id = :id",
                ['id' => $toAccountId, 'amount' => $amount]
            );

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
            $this->db->commit();



        } catch(\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }



    }

}
