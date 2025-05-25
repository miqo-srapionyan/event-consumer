<?php

declare(strict_types=1);

namespace App\Service;

use App\EventConsumer\EventConsumer;
use App\EventConsumer\Factory\EventSourceFactory;

readonly class EventSourceRegistry
{
    /**
     * @param \App\EventConsumer\EventConsumer              $eventConsumer
     * @param \App\EventConsumer\Factory\EventSourceFactory $eventSourceFactory
     */
    public function __construct(
        private EventConsumer $eventConsumer,
        private EventSourceFactory $eventSourceFactory
    ) {
        $this->registerSources();
    }

    private function registerSources(): void
    {
        foreach ($this->eventSourceFactory->createSources() as $source) {
            $this->eventConsumer->addEventSource($source);
        }
    }
}