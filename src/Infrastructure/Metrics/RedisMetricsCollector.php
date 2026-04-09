<?php

namespace App\Infrastructure\Metrics;

use App\Contracts\MetricsCollectorInterface;
use Redis;

class RedisMetricsCollector implements MetricsCollectorInterface
{
    public function __construct(private Redis $redis){}
    public function incrementTransferCount(string $status): void
    {
        $this->redis->incr('')
    }

    public function incrementRetryCount(): void
    {
        // TODO: Implement incrementRetryCount() method.
    }

    public function observeTransferDuration(float $seconds): string
    {
        // TODO: Implement observeTransferDuration() method.
    }
}
