<?php

namespace App\Infrastructure\Metrics;

use App\Contracts\MetricsCollectorInterface;
use Redis;

class RedisMetricsCollector implements MetricsCollectorInterface
{
    public function __construct(private Redis $redis){}
    public function incrementTransferCount(string $status): void
    {
        $this->redis->incr('transfer_count:' . strtolower($status));
    }

    public function incrementRetryCount(): void
    {
        $this->redis->incr('transfer_retry_count');
    }

    public function observeTransferDuration(float $seconds): string
    {
        // Временная реализация под текущий контракт интерфейса.
        // Позже можно заменить на histogram/summary через Prometheus.
        $key = 'transfer_duration_last_seconds';
        $this->redis->set($key, (string) $seconds);

        return $key;
    }
}
