# Laravel AMQP Message Broker

[![Latest Version on Packagist](https://img.shields.io/packagist/v/aachich/message-broker.svg?style=flat-square)](https://packagist.org/packages/aachich/message-broker)
[![PHP Version](https://img.shields.io/packagist/php-v/aachich/message-broker.svg?style=flat-square)](https://packagist.org/packages/aachich/message-broker)
[![License](https://img.shields.io/packagist/l/aachich/message-broker.svg?style=flat-square)](LICENSE)

A Laravel package providing an abstraction layer for AMQP message brokers. Easily switch between RabbitMQ and other AMQP-compatible brokers without changing your application code.

## Features

- 🔄 **Broker Abstraction** - Switch message brokers without code changes
- ⚡ **Fiber-based Async** - Non-blocking publish/consume using PHP 8.1+ Fibers
- 🔁 **Automatic Retries** - Configurable connection retry with exponential backoff
- 📦 **Bulk Publishing** - Efficiently publish collections of messages
- 💀 **Dead Letter Queue** - Automatic DLQ routing for failed messages
- 🎯 **Exchange Support** - Publish to queues or exchanges with routing keys

## Requirements

- PHP 8.1+
- Laravel 10.x or 11.x
- RabbitMQ (or any AMQP-compatible broker)

## Installation

```bash
composer require aachich/message-broker
```

The package will auto-register its service provider.

### Publish Configuration

```bash
php artisan vendor:publish --provider="Aachich\MessageBroker\MessageBrokerServiceProvider" --tag=config
```

### Environment Variables

Add these to your `.env` file:

```env
RABBITMQ_HOST=localhost
RABBITMQ_PORT=5672
RABBITMQ_USER=guest
RABBITMQ_PASSWORD=guest
RABBITMQ_VHOST=/
RABBITMQ_QUEUE_CONSUMER=my-consume-queue
RABBITMQ_QUEUE_PUBLISHER=my-publish-queue
RABBITMQ_QUEUE_REJECT=my-dlq
RABBITMQ_MAX_RETRIES=30
```

## Usage

### Basic Publishing

```php
use Aachich\MessageBroker\Facades\MessageBroker;

// Connect first
MessageBroker::connect();

// Publish a message
MessageBroker::publishToQueue($message, 'my-queue', ['header-key' => 'value']);

// Publish to an exchange
MessageBroker::publishToExchange($message, 'my-exchange', [], 'routing.key');
```

### Consuming Messages

```php
use Aachich\MessageBroker\Facades\MessageBroker;

MessageBroker::connect();

MessageBroker::consumeMessage('my-queue', function ($message) {
    // Process the message
    $body = $message->getBody();
    
    // Return true to acknowledge, false to reject
    return true;
});
```

### Bulk Publishing

```php
use Illuminate\Support\Collection;

$messages = collect(['msg1', 'msg2', 'msg3']);

MessageBroker::publishBulkMessagesToQueue($messages, 'my-queue');
```

### Check Connection Status

```php
$status = MessageBroker::getStatus();
// Returns: ['brokerName' => 'RabbitMQ', 'connect' => true, 'consuming' => false]
```

## Extending with Custom Brokers

To add support for a new AMQP broker:

1. Create a repository implementing `BrokerRepoInterface`:

```php
use Aachich\MessageBroker\Contracts\BrokerRepoInterface;

class MyCustomBrokerRepository implements BrokerRepoInterface
{
    // Implement all interface methods
}
```

2. Update the service provider binding based on config:

```php
$this->app->singleton(BrokerRepoInterface::class, function ($app) {
    return match(config('messagebroker.default')) {
        'rabbitmq' => new RabbitMQRepository(),
        'custom' => new MyCustomBrokerRepository(),
        default => throw new InvalidMessageBrokerNameException(),
    };
});
```

## Testing

```bash
vendor/bin/phpunit
```

## Contributing

Contributions are welcome! Please submit a PR against the `main` branch.

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

## License

This package is open-sourced software licensed under the [MIT license](LICENSE).