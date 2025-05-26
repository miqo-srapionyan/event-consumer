# Event Consumer System - Symfony Implementation

This repository contains a Symfony-based implementation of an event consuming system designed to collect events from multiple sources into a centralized storage. The system ensures that no conflicts occur during parallel execution, even when multiple instances are running on different servers. There must be at least a 200 ms interval between any two consecutive requests to the same event source.

## High level system design
```
                           +------------------------------------------------------------+
                           |                          Docker Network                    |
                           |                                                            |
                           |   +-------------+     +--------------+     +------------+  |
                           |   | Node.js     |     | Node.js      |     | Node.js    |  |
                           |   | Event Src 1 |     | Event Src 2  |     | Event Src N|  |
                           |   +-------------+     +--------------+     +------------+  |
                           |         |                     |                  |         |
                           |         +----------+----------+------------------+         |
                           |                    |                                       |
                           |       +------------------------------+                     |
                           |       |    Symfony Event Consumer    |                     |
                           |       |  +------------------------+  |                     |
                           |       |  | Event Loader           |  |                     |
                           |       |  |  - Fetch Events        |  |                     |
                           |       |  |  - Rate Limit (200ms)  |  |                     |
                           |       |  |  - Lock via Redis      |  |                     |
                           |       |  +------------------------+  |                     |
                           |       |  | Event Storage (DB)     |  |                     |
                           |       |  | Lock Manager (Redis)   |  |                     |
                           |       |  | RequestTimeStore(Redis)|  |                     |
                           |       |  +------------------------+  |                     |
                           |       +------------------------------+                     |
                           |                    |                                       |
                           |           +-----------------+   +------------------+       |
                           |           |      Redis      |   |     Database     |       |
                           |           +-----------------+   +------------------+       |
                           +------------------------------------------------------------+

```

## Features

- Round-robin loading from multiple event sources
- Concurrency control using Redis locks
- Rate limiting (minimum 200ms between requests to the same source)
- Error handling and logging
- Infinite loop processing

## Requirements

- [Docker](https://docs.docker.com/engine/install/) & [Docker Compose](https://docs.docker.com/compose/install/) **(required)** 
- PHP 8.1 or higher (via Docker)
- Symfony 6.0 or higher (via Docker)
- Redis server (via Docker)
- Node.js (used for mock servers, included in Docker)

## Installation

### 1. Clone the repository 
```bash
git clone git@github.com:miqo-srapionyan/event-consumer.git
```
```bash
cd event-consumer
```
### 2. Start the services
Make sure Docker is running, then start Redis, Node.js, and the PHP app container:

```bash
docker-compose up -d --build
```
This will:

- Build the PHP app container
- Start Redis server for locking and rate limiting
- Start the mock events servers (Node.js)
- Start event consumer servers (replica: 3)

To check logs of consumer you can run:
```bash
docker logs -f event-consumer-consumer-1
```

### Stop containers
```bash
docker-compose down
```

## Adding Event Sources

You can add new event sources by creating classes that implement the `EventSourceInterface` and tagging them with `app.event_source` in your service configuration:

```yaml
# config/services.yaml
parameters:
    app.event_sources:
        - { name: 'source1', url: 'http://event-source-1:3001/events' }
        - { name: 'source2', url: 'http://event-source-2:3002/events' }
        - { name: 'source3', url: 'http://event-source-3:3003/events' }
```

## Architecture

The system is built around the following key interfaces:

- `EventInterface`: Represents an event with unique ID, source name, and data.
- `EventSourceInterface`: Represents a source of events with methods to fetch events.
- `EventStorageInterface`: Responsible for storing events and tracking the last known event ID per source.
- `LockManagerInterface`: Provides locking mechanisms to prevent concurrent processing of the same events.
- `RequestTimeStoreInterface`: Tracks the last request time per event source to enforce rate limiting between requests.
- `EventConsumerInterface`: The main component that coordinates the event loading process.

## Testing

Run the tests with PHPUnit inside container:

```bash
docker exec -it event-consumer-consumer-1 bash
```
```bash
php bin/phpunit
```
## License

This project is licensed under the [MIT License](LICENSE).

Made with ❤️ by [Mikayel Srapionyan](https://github.com/miqo-srapionyan)
