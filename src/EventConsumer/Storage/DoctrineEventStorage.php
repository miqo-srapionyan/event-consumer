<?php

declare(strict_types=1);

namespace App\EventConsumer\Storage;

use App\EventConsumer\Interface\EventInterface;
use App\EventConsumer\Interface\EventStorageInterface;

readonly class DoctrineEventStorage implements EventStorageInterface
{
    public function storeEvent(EventInterface $event): bool
    {
        // assume we stored event in DB
        return true;
    }

    public function getLastKnownEventId(string $sourceName): int
    {
        // assume getting last event id
        return 1;
    }

    public function saveLastKnownEventId(string $sourceName, int $maxId): bool
    {
        return true;
    }
}