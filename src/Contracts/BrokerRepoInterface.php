<?php

namespace MaroEco\MessageBroker\Contracts;

use Illuminate\Support\Collection;
use PhpAmqpLib\Message\AMQPMessage;

interface BrokerRepoInterface
{
    public function connect(array $queuesName = [], array $exchangesName = [], array $bindExchangeQueues = []);
    public function consumeMessageFromQueue($queue, callable $callback);
    public function publishMessageToQueue($msg, string|null $queue = null, array|null $headers = null);
    public function publishMessageToExchange($msg, string|null $exchangeName = null, $exchangeType = 'fanout', array|null $headers = null);
    public function publishBulkMessagesToQueue(Collection $messages, $headers = null, $exchange = null ,$queue = null);
    public function status();
    public function isMessageRejectable($message): bool;
    public function rejectMessage($message): void;
    public function acknowledgeMessage(AMQPMessage $message);
    public function requeueNewMessage($message, $queue = false);
    public function consumeFromConsumerQueue(callable $callback);
}
