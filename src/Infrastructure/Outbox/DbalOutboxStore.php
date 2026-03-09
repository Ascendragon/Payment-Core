<?php

namespace App\Infrastructure\Outbox;

use Doctrine\DBAL\Connection;
use Symfony\Component\Uid\Uuid;

class DbalOutboxStore
{
    public function __construct(private Connection $db) {}
    public function save(object $message): void
    {
        $payload = json_encode($message);
        $eventClass = get_class($message);
        $id = Uuid::v4()->toRfc4122();
        $createdAt = date('Y-m-d H:i:s');
        $status = 'Pending';
        $sql = "INSERT INTO outbox_message(id,event_class, payload, status, created_at) VALUES(?,?,?,?,?)";

        $this->db->executeStatement($sql, [
            $id,
            $eventClass,
            $payload,
            $status,
            $createdAt
        ]);
    }
}
