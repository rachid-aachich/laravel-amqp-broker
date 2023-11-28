<?php

namespace MaroEco\MessageBroker\Contracts;

use Illuminate\Support\Collection;
use PhpAmqpLib\Message\AMQPMessage;

interface BrokerRepoInterface
{
    public function connect();
    public function consumeMessageFromQueue(string $consumeQueue, callable $callback): void;
    public function publishMessageToQueue(AMQPMessage $message, string $publishQueue);
    public function publishMessageToExchange(AMQPMessage $message, string $exchange);
    public function publishBulkMessagesToQueue(Collection $messages, string $queue);
    public function publishBulkMessagesToExchange(Collection $messages, string $exchange);
    public function status();
    public function acknowledgeMessage(AMQPMessage $message);
}
