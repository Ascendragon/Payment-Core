<?php

namespace App\MessageHandler;
use App\Domain\Transfer\Message\TransferCompletedMessage;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class TransferCompletedHandler
{
    public function __invoke(TransferCompletedMessage $message) {
        error_log("Асинхронная задача: Отправка email пользователю {$message->fromAccountId} о переводе суммы {$message->amount}");
    }
}
