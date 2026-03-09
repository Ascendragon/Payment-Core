<?php

namespace App\Infrastructure\MessageBroker;

use App\Contracts\MessageBrokerInterface;
use App\Domain\Transfer\Message\TransferCompletedMessage;
use Symfony\Component\Messenger\MessageBusInterface;

class RabbitMQMessengerBroker implements MessageBrokerInterface
{
    public function __construct(private MessageBusInterface $bus) {}
    public function dispatch(object $message): void
    {
            $this->bus->dispatch($message);
    }
}
