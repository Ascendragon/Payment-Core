<?php

namespace App\Contracts;

interface MetricsCollectorInterface
{
    public function incrementTransferCount(string $status): void;

    public function incrementRetryCount(): void;

    public function observeTransferDuration(float $seconds): string;
}
