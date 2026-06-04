<?php

namespace App\Infrastructure\MessageBroker;

use App\Contracts\MessageBrokerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class RabbitMQMessengerBroker implements MessageBrokerInterface
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function dispatch(object $message): void
    {
        $this->bus->dispatch($message);
    }
}
