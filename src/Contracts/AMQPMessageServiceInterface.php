<?php

namespace MaroEco\MessageBroker\Contracts;

use Illuminate\Support\Collection;
use PhpAmqpLib\Message\AMQPMessage;

interface AMQPMessageServiceInterface
{
    public function validateMessage(AMQPMessage $message, array $headers = []): bool;

    public function takeMessage(AMQPMessage $message);

    public function requeueNewMessage(AMQPMessage $message, string $queue);

    public function connect();

    public function consumeMessageFromQueue(string $consumeQueue, callable $callback): void;

    public function publishMessageToQueue($message, string $queue, array $headers = []);

    public function publishBulkMessagesToQueue(Collection $message, string $queue, array $headers = []);

    public function publishBulkMessagesToExchange(Collection $messages, string $exchange, array $headers = []);

    public function publishMessageToExchange($message, string $exchangeName, array $headers = []);

    public function getStatus();
}
