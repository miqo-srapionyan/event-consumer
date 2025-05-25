<?php

declare(strict_types=1);

namespace App\EventConsumer\Interface;

interface RequestTimeStoreInterface
{
    public function getLastRequestTime(string $sourceName): float;

    public function updateLastRequestTime(string $sourceName, float $timestamp): void;
}