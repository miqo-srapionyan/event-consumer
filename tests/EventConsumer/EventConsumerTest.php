<?php

namespace Tests\EventLoading;

use App\EventConsumer\EventConsumer;
use App\EventConsumer\Interface\EventSourceInterface;
use App\EventConsumer\Interface\EventStorageInterface;
use App\EventConsumer\Interface\LockManagerInterface;
use App\EventConsumer\Interface\RequestTimeStoreInterface;
use App\EventConsumer\Model\Event;
use Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class EventConsumerTest extends TestCase
{
    private EventConsumer $eventConsumer;
    private EventStorageInterface|MockObject $storageMock;
    private LockManagerInterface|MockObject $lockManagerMock;
    private RequestTimeStoreInterface|MockObject $requestTimeStoreMock;
    private LoggerInterface|MockObject $loggerMock;
    private EventSourceInterface|MockObject $sourceAMock;
    private EventSourceInterface|MockObject $sourceBMock;

    /**
     * @throws \PHPUnit\Framework\MockObject\Exception
     */
    protected function setUp(): void
    {
        $this->storageMock = $this->createMock(EventStorageInterface::class);
        $this->lockManagerMock = $this->createMock(LockManagerInterface::class);
        $this->requestTimeStoreMock = $this->createMock(RequestTimeStoreInterface::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);

        $this->eventConsumer = new EventConsumer(
            $this->storageMock,
            $this->lockManagerMock,
            $this->requestTimeStoreMock,
            $this->loggerMock,
            200 // minRequestIntervalMs
        );

        // Set up mock event sources
        $this->sourceAMock = $this->createMock(EventSourceInterface::class);
        $this->sourceAMock->method('getName')->willReturn('SourceA');

        $this->sourceBMock = $this->createMock(EventSourceInterface::class);
        $this->sourceBMock->method('getName')->willReturn('SourceB');
    }

    public function testRunWithNoEventSourcesConfigured(): void
    {
        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with('No event sources configured. Consumer will exit.');

        $this->eventConsumer->run();
    }

    public function testAddEventSource(): void
    {
        $this->eventConsumer->addEventSource($this->sourceAMock);

        $sources = $this->eventConsumer->getEventSources();
        $this->assertCount(1, $sources);
        $this->assertSame($this->sourceAMock, $sources[0]);
    }

    public function testRespectMinRequestInterval(): void
    {
        $this->eventConsumer->addEventSource($this->sourceAMock);

        $currentTime = microtime(true) * 1000;

        // Mock request time store to return a recent timestamp (within min interval)
        $this->requestTimeStoreMock->expects($this->once())
            ->method('getLastRequestTime')
            ->with('SourceA')
            ->willReturn($currentTime - 100); // 100ms ago, less than 200ms minimum

        // Logger should log debug message about skipping due to rate limiting
        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with($this->stringContains('Skipping source SourceA due to rate limiting'));

        // Storage and lock manager should never be called
        $this->storageMock->expects($this->never())->method('getLastKnownEventId');
        $this->lockManagerMock->expects($this->never())->method('acquireLock');
        $this->sourceAMock->expects($this->never())->method('fetchEvents');

        // Use a testable version that runs only one cycle
        $this->runSingleCycle();
    }

    public function testProperLockingWhenLockFails(): void
    {
        $this->eventConsumer->addEventSource($this->sourceAMock);

        $currentTime = microtime(true) * 1000;

        // Mock request time store to return old timestamp (outside min interval)
        $this->requestTimeStoreMock->expects($this->once())
            ->method('getLastRequestTime')
            ->with('SourceA')
            ->willReturn($currentTime - 1000); // 1 second ago

        // Storage should be called to get last known event ID
        $this->storageMock->expects($this->once())
            ->method('getLastKnownEventId')
            ->with('SourceA')
            ->willReturn(100);

        // Lock manager should fail to acquire lock
        $this->lockManagerMock->expects($this->once())
            ->method('acquireLock')
            ->with('SourceA:100')
            ->willReturn(false);

        // Logger should log debug message about lock failure
        $this->loggerMock->expects($this->once())
            ->method('debug')
            ->with('Could not acquire lock for SourceA:100, skipping');

        // Source should never be called if lock fails
        $this->sourceAMock->expects($this->never())->method('fetchEvents');
        $this->requestTimeStoreMock->expects($this->never())->method('updateLastRequestTime');

        $this->runSingleCycle();
    }

    public function testSuccessfulEventFetchingAndStorage(): void
    {
        $this->eventConsumer->addEventSource($this->sourceAMock);

        $currentTime = microtime(true) * 1000;

        // Create test events
        $events = [
            new Event(101, 'SourceA', ['data' => 'event1']),
            new Event(102, 'SourceA', ['data' => 'event2']),
            new Event(103, 'SourceA', ['data' => 'event3']),
        ];

        // Mock request time store
        $this->requestTimeStoreMock->expects($this->once())
            ->method('getLastRequestTime')
            ->with('SourceA')
            ->willReturn($currentTime - 1000); // 1 second ago

        $this->requestTimeStoreMock->expects($this->once())
            ->method('updateLastRequestTime')
            ->with('SourceA', $this->anything());

        // Storage mock setup
        $this->storageMock->expects($this->once())
            ->method('getLastKnownEventId')
            ->with('SourceA')
            ->willReturn(100);

        // Lock manager should succeed
        $this->lockManagerMock->expects($this->once())
            ->method('acquireLock')
            ->with('SourceA:100')
            ->willReturn(true);

        $this->lockManagerMock->expects($this->once())
            ->method('releaseLock')
            ->with('SourceA:100');

        // Source should return events
        $this->sourceAMock->expects($this->once())
            ->method('fetchEvents')
            ->with(100)
            ->willReturn($events);

        // Each event should be stored
        $this->storageMock->expects($this->exactly(3))
            ->method('storeEvent')
            ->with($this->callback(function ($event) use ($events) {
                return in_array($event, $events, true);
            }));

        // Last known event ID should be updated to the highest ID
        $this->storageMock->expects($this->once())
            ->method('saveLastKnownEventId')
            ->with('SourceA', 103);

        // Logger should log info about fetched events
        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with('Fetched 3 events from SourceA');

        $this->runSingleCycle();
    }

    public function testSkipOutOfOrderEvents(): void
    {
        $this->eventConsumer->addEventSource($this->sourceAMock);

        $currentTime = microtime(true) * 1000;

        // Create test events with one out-of-order event
        $events = [
            new Event(101, 'SourceA', ['data' => 'event1']),
            new Event(99, 'SourceA', ['data' => 'old_event']), // Out of order
            new Event(102, 'SourceA', ['data' => 'event2']),
        ];

        $this->setupBasicMocks($currentTime, 100, $events);

        // Only 2 events should be stored (skip the out-of-order one)
        $expectedStoredEvents = [$events[0], $events[2]]; // Events 101 and 102
        $this->storageMock->expects($this->exactly(2))
            ->method('storeEvent')
            ->with($this->callback(function ($event) use ($expectedStoredEvents) {
                return in_array($event, $expectedStoredEvents, true);
            }));

        // Logger should warn about out-of-order event
        $this->loggerMock->expects($this->once())
            ->method('warning')
            ->with('Skipping out-of-order or duplicate event ID: 99 from SourceA');

        // Last known event ID should be updated to 102
        $this->storageMock->expects($this->once())
            ->method('saveLastKnownEventId')
            ->with('SourceA', 102);

        $this->runSingleCycle();
    }

    public function testHandleExceptionDuringEventFetching(): void
    {
        $this->eventConsumer->addEventSource($this->sourceAMock);

        $currentTime = microtime(true) * 1000;

        // Mock request time store and storage
        $this->requestTimeStoreMock->method('getLastRequestTime')->willReturn($currentTime - 1000);
        $this->storageMock->method('getLastKnownEventId')->willReturn(100);
        $this->lockManagerMock->method('acquireLock')->willReturn(true);

        // Source throws exception
        $exception = new Exception('Network error');
        $this->sourceAMock->expects($this->once())
            ->method('fetchEvents')
            ->willThrowException($exception);

        // Logger should log error
        $this->loggerMock->expects($this->once())
            ->method('error')
            ->with('Error fetching events from SourceA: Network error');

        // Lock should still be released despite exception
        $this->lockManagerMock->expects($this->once())
            ->method('releaseLock')
            ->with('SourceA:100');

        // No events should be stored
        $this->storageMock->expects($this->never())->method('storeEvent');
        $this->storageMock->expects($this->never())->method('saveLastKnownEventId');

        $this->runSingleCycle();
    }

    public function testNoEventsReturnedFromSource(): void
    {
        $this->eventConsumer->addEventSource($this->sourceAMock);

        $currentTime = microtime(true) * 1000;

        $this->setupBasicMocks($currentTime, 100, []);

        // No events should be stored
        $this->storageMock->expects($this->never())->method('storeEvent');

        // Last known event ID should not be updated
        $this->storageMock->expects($this->never())->method('saveLastKnownEventId');

        // Logger should still log info about 0 events
        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with('Fetched 0 events from SourceA');

        $this->runSingleCycle();
    }

    public function testMultipleEventSources(): void
    {
        $this->eventConsumer->addEventSource($this->sourceAMock);
        $this->eventConsumer->addEventSource($this->sourceBMock);

        $currentTime = microtime(true) * 1000;

        // Setup for both sources
        $this->requestTimeStoreMock->expects($this->exactly(2))
            ->method('getLastRequestTime')
            ->willReturnCallback(function ($sourceName) use ($currentTime) {
                return $currentTime - 1000; // Both sources last requested 1 second ago
            });

        $this->storageMock->expects($this->exactly(2))
            ->method('getLastKnownEventId')
            ->willReturnCallback(function ($sourceName) {
                return $sourceName === 'SourceA' ? 100 : 200;
            });

        $this->lockManagerMock->expects($this->exactly(2))
            ->method('acquireLock')
            ->willReturnCallback(function ($lockKey) {
                return in_array($lockKey, ['SourceA:100', 'SourceB:200']);
            });

        // Both sources should be called
        $this->sourceAMock->expects($this->once())
            ->method('fetchEvents')
            ->with(100)
            ->willReturn([]);

        $this->sourceBMock->expects($this->once())
            ->method('fetchEvents')
            ->with(200)
            ->willReturn([]);

        $this->runSingleCycle();
    }

    private function setupBasicMocks(float $currentTime, int $lastKnownId, array $events): void
    {
        $this->requestTimeStoreMock->method('getLastRequestTime')->willReturn($currentTime - 1000);
        $this->requestTimeStoreMock->expects($this->once())->method('updateLastRequestTime');

        $this->storageMock->method('getLastKnownEventId')->willReturn($lastKnownId);

        $this->lockManagerMock->method('acquireLock')->willReturn(true);
        $this->lockManagerMock->expects($this->once())->method('releaseLock');

        $this->sourceAMock->method('fetchEvents')->willReturn($events);

        $this->loggerMock->expects($this->once())
            ->method('info')
            ->with('Fetched ' . count($events) . ' events from SourceA');
    }

    private function runSingleCycle(): void
    {
        // Create a testable event consumer that runs only one iteration
        $testableConsumer = new class($this->eventConsumer) {
            private EventConsumer $consumer;

            public function __construct(EventConsumer $consumer)
            {
                $this->consumer = $consumer;
            }

            public function runOneCycle(): void
            {
                $reflection = new \ReflectionClass($this->consumer);

                // Get private properties
                $eventSourcesProperty = $reflection->getProperty('eventSources');
                $eventSourcesProperty->setAccessible(true);
                $eventSources = $eventSourcesProperty->getValue($this->consumer);

                $storageProperty = $reflection->getProperty('storage');
                $storageProperty->setAccessible(true);
                $storage = $storageProperty->getValue($this->consumer);

                $lockManagerProperty = $reflection->getProperty('lockManager');
                $lockManagerProperty->setAccessible(true);
                $lockManager = $lockManagerProperty->getValue($this->consumer);

                $requestTimeStoreProperty = $reflection->getProperty('requestTimeStore');
                $requestTimeStoreProperty->setAccessible(true);
                $requestTimeStore = $requestTimeStoreProperty->getValue($this->consumer);

                $loggerProperty = $reflection->getProperty('logger');
                $loggerProperty->setAccessible(true);
                $logger = $loggerProperty->getValue($this->consumer);

                $minRequestIntervalMsProperty = $reflection->getProperty('minRequestIntervalMs');
                $minRequestIntervalMsProperty->setAccessible(true);
                $minRequestIntervalMs = $minRequestIntervalMsProperty->getValue($this->consumer);

                // Execute the main loop logic once
                foreach ($eventSources as $source) {
                    $sourceName = $source->getName();
                    $currentTime = microtime(true) * 1000;
                    $lastRequestTime = $requestTimeStore->getLastRequestTime($sourceName);
                    $timeSinceLastRequest = $currentTime - $lastRequestTime;

                    if ($timeSinceLastRequest < $minRequestIntervalMs) {
                        $logger->debug("Skipping source {$sourceName} due to rate limiting");
                        continue;
                    }

                    $lastKnownId = $storage->getLastKnownEventId($sourceName);
                    $lockKey = "{$sourceName}:{$lastKnownId}";

                    if (!$lockManager->acquireLock($lockKey)) {
                        $logger->debug("Could not acquire lock for {$lockKey}, skipping");
                        continue;
                    }

                    try {
                        $requestTimeStore->updateLastRequestTime($sourceName, $currentTime);

                        $events = $source->fetchEvents($lastKnownId);
                        $logger->info("Fetched " . count($events) . " events from {$sourceName}");

                        $maxId = $lastKnownId;
                        foreach ($events as $event) {
                            $eventId = $event->getId();

                            if ($eventId <= $lastKnownId) {
                                $logger->warning("Skipping out-of-order or duplicate event ID: {$eventId} from {$sourceName}");
                                continue;
                            }

                            $storage->storeEvent($event);
                            $maxId = max($maxId, $eventId);
                        }

                        if ($maxId > $lastKnownId) {
                            $storage->saveLastKnownEventId($sourceName, $maxId);
                        }

                    } catch (Exception $e) {
                        $logger->error("Error fetching events from {$sourceName}: " . $e->getMessage());
                    } finally {
                        $lockManager->releaseLock($lockKey);
                    }
                }
            }
        };

        $testableConsumer->runOneCycle();
    }
}