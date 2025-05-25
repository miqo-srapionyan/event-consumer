<?php

declare(strict_types=1);

namespace App\EventConsumer\Interface;

interface LockManagerInterface
{
    /**
     * Acquire a lock for a specific resource.
     *
     * @param string $resource Resource identifier to lock
     * @param int $ttlSeconds Time-to-live for the lock in seconds
     * @return bool True if lock was acquired, false otherwise
     */
    public function acquireLock(string $resource, int $ttlSeconds = 30): bool;

    /**
     * Release a lock for a specific resource.
     *
     * @param string $resource Resource identifier to unlock
     * @return bool True if lock was released, false otherwise
     */
    public function releaseLock(string $resource): bool;
}