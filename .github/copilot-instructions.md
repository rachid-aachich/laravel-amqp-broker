# Copilot Instructions for Laravel AMQP Message Broker

## Architecture Overview

This is a Laravel package providing a message broker abstraction layer. Currently supports **RabbitMQ** via `php-amqplib`. Requires **PHP 8.1+** for Fiber support.

**Layered Architecture:**
```
MessageBroker Facade → MessageBrokerInterface (public API)
    └── MessageBrokerService (orchestration + Fiber-based async)
        └── AMQPMessageService (message creation/validation)
            └── AMQPHelperService (AMQP utilities)
                └── RabbitMQRepository (low-level AMQP operations)
```

Key interfaces in [src/Contracts/](src/Contracts/):
- `MessageBrokerInterface` - Public API for consuming/publishing
- `BrokerRepoInterface` - Broker-specific implementations (extend this for new brokers)
- `AMQPMessageServiceInterface` / `AMQPHelperServiceInterface` - AMQP-specific helpers

## Key Patterns

### Dependency Injection via Interfaces
All bindings are singletons registered in [MessageBrokerServiceProvider.php](src/MessageBrokerServiceProvider.php):
```php
$this->app->singleton(BrokerRepoInterface::class, RabbitMQRepository::class);
```
Always inject interfaces, not concrete classes.

### Facade Usage
Use `Aachich\MessageBroker\Facades\MessageBroker` for static access - see [src/Facades/MessageBroker.php](src/Facades/MessageBroker.php).

### Fiber-Based Async Processing
`MessageBrokerService` uses PHP Fibers for non-blocking publish/consume. See `publishToQueue()` and `consumeMessage()` patterns - wrap operations in `new Fiber(...)`.

### Message Persistence
All messages use `delivery_mode: 2` (persistent). Use `AMQPHelperService::createPersistenceAMQPMessage()` for message creation.

### Retry & Dead Letter Queue
- Messages exceeding `maxRMQDeliveryLimit` are rejected to DLX
- Connection retries configured via `maxRMQConnectionRetries` / `maxRMQConnectionRetryDelay`
- See [src/config/messagebroker.php](src/config/messagebroker.php) for all settings

## Development Workflow

### Testing
```bash
vendor/bin/phpunit
```
Tests use PHPUnit + Mockery. Mock `AMQPMessageServiceInterface` for service tests - see [tests/Unit/MessageBrokerServiceTest.php](tests/Unit/MessageBrokerServiceTest.php) for patterns.

### Required ENV Variables
```
RABBITMQ_HOST, RABBITMQ_PORT, RABBITMQ_USER, RABBITMQ_PASSWORD
RABBITMQ_VHOST, RABBITMQ_QUEUE_CONSUMER, RABBITMQ_QUEUE_PUBLISHER, RABBITMQ_QUEUE_REJECT
```

## Adding New Broker Support

1. Create new repository implementing `BrokerRepoInterface` in `src/Repositories/`
2. Update `MessageBrokerServiceProvider` to conditionally bind based on `config('messagebroker.default')`
3. Add broker-specific config section in [src/config/messagebroker.php](src/config/messagebroker.php)

## Code Conventions

- Namespace: `Aachich\MessageBroker\`
- Static connection/channel in repositories (`self::$connection`, `self::$channel`)
- Log channels: `['stdout']` for info, `['rabbitmq', 'stderr']` for errors
- PHPDoc all public methods with `@param` and `@return`
