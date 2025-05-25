<?php

declare(strict_types=1);

namespace App\EventConsumer\Infrastructure;

use App\EventConsumer\Interface\LockManagerInterface;
use Redis;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class RedisLockManager implements LockManagerInterface
{
    private const LOCK_PREFIX = 'event_consumer_lock:';

    public function __construct(
        private readonly Redis $redis,
        #[Autowire('%redis_lock_ttl%')]
        private readonly int $ttlSeconds = 30
    ) {
    }

    public function acquireLock(string $resource, int $ttlSeconds = null): bool
    {
        $ttl = $ttlSeconds ?? $this->ttlSeconds;
        $lockKey = self::LOCK_PREFIX . $resource;
        // NX = Only set the key if it does not already exist
        // EX = Set the specified expire time, in seconds
        return (bool)$this->redis->set($lockKey, '1', ['NX', 'EX' => $ttl]);
    }

    public function releaseLock(string $resource): bool
    {
        $lockKey = self::LOCK_PREFIX . $resource;
        return (bool)$this->redis->del($lockKey);
    }
}