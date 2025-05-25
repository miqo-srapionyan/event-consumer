<?php

declare(strict_types=1);

namespace App\EventConsumer\Interface;

use Exception;

interface EventSourceInterface
{
    /**
     * Get the unique name of this event source.
     *
     * @return string
     */
    public function getName(): string;

    /**
     * Fetch events from the source with ID greater than the last known ID.
     *
     * @param int $lastKnownId The last known event ID from this source
     * @return EventInterface[] Array of events sorted by ID
     * @throws Exception If fetching fails due to network or server errors
     */
    public function fetchEvents(int $lastKnownId = 0): array;
}