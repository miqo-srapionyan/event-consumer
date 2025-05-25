<?php

declare(strict_types=1);

namespace App\EventConsumer\Interface;

interface EventConsumerInterface
{
    /**
     * Start the continuous event loading process.
     * This method runs in an infinite loop, querying event sources in a round-robin fashion.
     *
     * @return void
     */
    public function run(): void;

    /**
     * Add an event source to the consumer.
     *
     * @param \App\EventConsumer\Interface\EventSourceInterface $eventSource
     *
     * @return void
     */
    public function addEventSource(EventSourceInterface $eventSource): void;
}