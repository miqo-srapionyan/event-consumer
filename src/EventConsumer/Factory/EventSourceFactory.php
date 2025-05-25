<?php

declare(strict_types=1);

namespace App\EventConsumer\Factory;

use App\EventConsumer\Source\ApiEventSource;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class EventSourceFactory
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private array $sourceConfig
    ) {}

    /**
     * @return array
     */
    public function createSources(): array
    {
        $sources = [];

        foreach ($this->sourceConfig as $config) {
            $sources[] = new ApiEventSource(
                $this->httpClient,
                $config['name'],
                $config['url']
            );
        }

        return $sources;
    }
}