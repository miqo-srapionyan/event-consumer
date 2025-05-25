<?php

declare(strict_types=1);

namespace App\EventConsumer\Source;

use App\EventConsumer\Exception\ApiRequestException;
use App\EventConsumer\Exception\InvalidApiResponseException;
use App\EventConsumer\Interface\EventSourceInterface;
use App\EventConsumer\Model\Event;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\HttpClient\HttpClientInterface;

use function is_array;
use function is_numeric;

readonly class ApiEventSource implements EventSourceInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $name,
        private string $apiUrl
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Fetch events from the API source.
     *
     * @param int $lastKnownId
     *
     * @return array|\App\EventConsumer\Interface\EventInterface[]
     * @throws \Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface
     * @throws \Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface
     * @throws \Exception
     */
    public function fetchEvents(int $lastKnownId = 0): array
    {
        $response = $this->httpClient->request('GET', $this->apiUrl, [
            'query'   => [
                'lastId' => $lastKnownId,
                'limit'  => 1000,
            ],
            'timeout' => 5, // 5 second timeout
        ]);

        if ($response->getStatusCode() !== Response::HTTP_OK) {
            throw new ApiRequestException($response->getStatusCode(), $this->apiUrl);
        }

        $data = $response->toArray();

        if (!isset($data['events']) || !is_array($data['events'])) {
            throw new InvalidApiResponseException("Invalid response format from API for URL: ".$this->apiUrl);
        }

        $events = [];
        foreach ($data['events'] as $eventData) {
            if (!isset($eventData['id']) || !is_numeric($eventData['id'])) {
                continue; // Skip invalid events
            }

            $events[] = new Event(
                (int)$eventData['id'],
                $this->name,
                $eventData
            );
        }

        return $events;
    }
}