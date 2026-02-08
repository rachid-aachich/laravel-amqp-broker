<?php

namespace Aachich\MessageBroker\Facades;

use Illuminate\Support\Facades\Facade;
use Aachich\MessageBroker\Contracts\MessageBrokerInterface;

/**
 * @method static void connect()
 * @method static void consumeMessage(string $consumeQueue, callable $callback)
 * @method static void publishToQueue(mixed $messageContent, string $queue, array $headers = [])
 * @method static void publishToExchange(mixed $messageContent, string $exchangeName, array $headers = [], string $routingKey = "")
 * @method static void publishBulkMessagesToQueue(\Illuminate\Support\Collection $messages, string $queue, array $headers = [])
 * @method static void publishBulkMessagesToExchange(\Illuminate\Support\Collection $messages, string $exchange, array $headers = [])
 * @method static array getStatus()
 *
 * @see \Aachich\MessageBroker\Services\MessageBrokerService
 */
class MessageBroker extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return MessageBrokerInterface::class;
    }
}
