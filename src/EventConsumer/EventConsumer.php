<?php

declare(strict_types=1);

namespace App\EventConsumer;

use App\EventConsumer\Interface\EventConsumerInterface;
use App\EventConsumer\Interface\EventSourceInterface;
use App\EventConsumer\Interface\EventStorageInterface;
use App\EventConsumer\Interface\LockManagerInterface;
use App\EventConsumer\Interface\RequestTimeStoreInterface;
use Exception;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

use function count;
use function usleep;
use function microtime;

class EventConsumer implements EventConsumerInterface
{
    /**
     * @var EventSourceInterface[] Array of event sources
     */
    private array $eventSources = [];

    public function __construct(
        private readonly EventStorageInterface $storage,
        private readonly LockManagerInterface $lockManager,
        private readonly RequestTimeStoreInterface $requestTimeStore,
        private readonly LoggerInterface $logger,
        #[Autowire('%min_request_interval_ms%')]
        private readonly int $minRequestIntervalMs = 200
    ) {
    }

    public function addEventSource(EventSourceInterface $eventSource): void
    {
        $this->eventSources[] = $eventSource;
    }

    public function getEventSources(): array
    {
        return $this->eventSources;
    }

    public function run(): void
    {
        if (empty($this->eventSources)) {
            $this->logger->warning('No event sources configured. Consumer will exit.');
            return;
        }

        $this->logger->info('Starting event consumer with ' . count($this->eventSources) . ' sources');

        // Infinite loop as required by specifications
        while (true) {
            foreach ($this->eventSources as $source) {
                $sourceName = $source->getName();
                $currentTime = microtime(true) * 1000;
                $lastRequestTime = $this->requestTimeStore->getLastRequestTime($sourceName);
                $timeSinceLastRequest = $currentTime - $lastRequestTime;

                if ($timeSinceLastRequest < $this->minRequestIntervalMs) {
                    $this->logger->debug("Skipping source {$sourceName} due to rate limiting");
                    continue;
                }

                $lastKnownId = $this->storage->getLastKnownEventId($sourceName);
                $lockKey = "{$sourceName}:{$lastKnownId}";

                if (!$this->lockManager->acquireLock($lockKey)) {
                    $this->logger->debug("Could not acquire lock for {$lockKey}, skipping");
                    continue;
                }

                try {
                    $this->requestTimeStore->updateLastRequestTime($sourceName, $currentTime);

                    $events = $source->fetchEvents($lastKnownId);
                    $this->logger->info("Fetched " . count($events) . " events from {$sourceName}");

                    $maxId = $lastKnownId;
                    foreach ($events as $event) {
                        $eventId = $event->getId(); // assuming int ID

                        if ($eventId <= $lastKnownId) {
                            $this->logger->warning("Skipping out-of-order or duplicate event ID: {$eventId} from {$sourceName}");
                            continue;
                        }

                        $this->storage->storeEvent($event);
                        $maxId = max($maxId, $eventId);
                    }

                    // Update only after successful processing of the batch
                    if ($maxId > $lastKnownId) {
                        $this->storage->saveLastKnownEventId($sourceName, $maxId);
                    }

                } catch (Exception $e) {
                    $this->logger->error("Error fetching events from {$sourceName}: " . $e->getMessage());
                } finally {
                    $this->lockManager->releaseLock($lockKey);
                }
            }

            // Small sleep to prevent CPU overload in the infinite loop
            usleep(1000000); // 100ms sleep
        }
    }
}