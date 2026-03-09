<?php

namespace App\Contracts;

interface MessageBrokerInterface
{
    public function dispatch(object $message): void;
}
