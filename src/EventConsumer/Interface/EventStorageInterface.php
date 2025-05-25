<?php

declare(strict_types=1);

namespace App\EventConsumer\Interface;

interface EventStorageInterface
{
    /**
     * Store event in the storage.
     */
    public function storeEvent(EventInterface $event): bool;

    /**
     * Get the last known event ID for a specific source.
     *
     * @param string $sourceName The name of the event source
     * @return int The last known event ID or 0 if no events exist
     */
    public function getLastKnownEventId(string $sourceName): int;

    /**
     * Save the last known event ID for a specific source.
     *
     * @param string $sourceName
     * @param int    $maxId
     *
     * @return bool
     */
    public function saveLastKnownEventId(string $sourceName, int $maxId): bool;
}