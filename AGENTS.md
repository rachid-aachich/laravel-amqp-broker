# AGENTS.md - Instructions for AI Coding Agents

This file provides context for AI agents (GitHub Copilot, Claude, Cursor, etc.) working on this codebase.

## Project Summary

**Laravel AMQP Message Broker** - A Laravel package providing message broker abstraction for AMQP-compatible systems (RabbitMQ, etc.).

| Attribute | Value |
|-----------|-------|
| Package | `aachich/message-broker` |
| Namespace | `Aachich\MessageBroker` |
| PHP Version | 8.1+ (Fibers required) |
| Laravel | 10.x, 11.x |
| License | MIT |

## Architecture

```
Facade → Interface → Service → Helper → Repository
```

### Layer Responsibilities

1. **Facade** (`src/Facades/MessageBroker.php`) - Static access point
2. **MessageBrokerService** - Fiber-based async orchestration
3. **AMQPMessageService** - Message creation, validation, delegation
4. **AMQPHelperService** - AMQP utilities (headers, persistence)
5. **RabbitMQRepository** - Low-level php-amqplib operations

## Key Patterns

### Dependency Injection
All bindings are singletons in `MessageBrokerServiceProvider`:
```php
$this->app->singleton(BrokerRepoInterface::class, RabbitMQRepository::class);
```

### Fiber Async
`MessageBrokerService` wraps operations in PHP Fibers:
```php
$fiber = new Fiber(function () use ($message, $queue) {
    $this->amqpMessageService->publishMessageToQueue($message, $queue);
});
$fiber->start();
```

### Message Persistence
All messages use `delivery_mode: 2` (persistent). See `AMQPHelperService::createPersistenceAMQPMessage()`.

### Static Connection
Repository uses static `$connection` and `$channel` for connection reuse.

## File Structure

```
src/
├── Contracts/           # Interfaces
├── Exceptions/          # Custom exceptions
├── Facades/             # Laravel Facade
├── Repositories/        # Broker implementations
├── Services/            # Business logic
├── config/              # Package config
└── MessageBrokerServiceProvider.php

tests/Unit/              # PHPUnit tests
```

## Commands

```bash
# Run tests
vendor/bin/phpunit

# Publish config (in Laravel app)
php artisan vendor:publish --provider="Aachich\MessageBroker\MessageBrokerServiceProvider"
```

## Adding New Broker

1. Create `src/Repositories/NewBrokerRepository.php` implementing `BrokerRepoInterface`
2. Add conditional binding in `MessageBrokerServiceProvider`
3. Add config section in `src/config/messagebroker.php`

## Code Style

- PSR-4 autoloading
- PHPDoc on all public methods
- Type declarations on parameters and returns
- Mockery for test mocking

## Important Interfaces

### BrokerRepoInterface
```php
connect(), closeConnection(), consumeMessageFromQueue(), 
publishMessageToQueue(), publishMessageToExchange(),
publishBulkMessagesToQueue(), publishBulkMessagesToExchange(),
acknowledgeMessage(), rejectMessage(), status()
```

### MessageBrokerInterface
```php
connect(), consumeMessage(), publishToQueue(), publishToExchange(),
publishBulkMessagesToQueue(), publishBulkMessagesToExchange(), getStatus()
```

## Testing Notes

- Tests use PHPUnit 9.x + Mockery
- Mock `AMQPMessageServiceInterface` for service tests
- Use Reflection to access static properties in repository tests
- Laravel facades not available in unit tests (no app context)
