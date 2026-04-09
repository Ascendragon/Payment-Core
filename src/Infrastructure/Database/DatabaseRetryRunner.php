<?php

namespace App\Infrastructure\Database;

use App\Domain\Transfer\Exception\ConcurrencyConflictException;
use Doctrine\DBAL\Connection;

class DatabaseRetryRunner
{
    public function __construct(private readonly Connection $db){}

    public function run(callable $action,int $maxRetries = 3): mixed
    {
        $attempt = 1;

        while(true){
            $this->db->beginTransaction();
            try {
                $result = $action();
                $this->db->commit();
                return $result;
            } catch(ConcurrencyConflictException $e) {
                $this->db->rollBack();

                if($attempt === $maxRetries){
                    throw $e;
                }
                $attempt++;
                usleep(100000);
            } catch(\Throwable $e) {
                $this->db->rollBack();
                throw $e;
            }
        }
    }
}
