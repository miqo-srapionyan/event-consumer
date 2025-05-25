<?php

declare(strict_types=1);

namespace App\EventConsumer\Model;

use App\EventConsumer\Interface\EventInterface;

readonly class Event implements EventInterface
{
    public function __construct(
        private int $id,
        private string $sourceName,
        private array $data
    ) {
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getSourceName(): string
    {
        return $this->sourceName;
    }

    public function getData(): array
    {
        return $this->data;
    }
}