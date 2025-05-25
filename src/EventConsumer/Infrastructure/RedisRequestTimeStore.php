<?php

declare(strict_types=1);

namespace App\EventConsumer\Infrastructure;

use App\EventConsumer\Interface\RequestTimeStoreInterface;
use Redis;

class RedisRequestTimeStore implements RequestTimeStoreInterface
{
    private const PREFIX = 'last_request_time:';

    public function __construct(private readonly Redis $redis) {}

    public function getLastRequestTime(string $sourceName): float
    {
        $value = $this->redis->get(self::PREFIX . $sourceName);

        return $value !== false ? (float) $value : 0;
    }

    public function updateLastRequestTime(string $sourceName, float $timestamp): void
    {
        $this->redis->set(self::PREFIX . $sourceName, $timestamp);
    }
}