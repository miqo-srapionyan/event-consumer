<?php

declare(strict_types=1);

namespace App\EventConsumer\Interface;

interface EventInterface
{
    /**
     * Get the unique identifier of the event within its source.
     *
     * @return int
     */
    public function getId(): int;

    /**
     * Get the source name this event belongs to.
     *
     * @return string
     */
    public function getSourceName(): string;

    /**
     * Get the event data.
     *
     * @return array
     */
    public function getData(): array;
}