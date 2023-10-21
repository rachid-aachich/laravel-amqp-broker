<?php

namespace MaroEco\MessageBroker\Contracts;

use PhpAmqpLib\Message\AMQPMessage;

interface BrokerRepoInterface
{
    public function connect();
    public function consumeMessageFromQueue($queue, callable $callback);
    public function publishMessageToQueue($msg, $queue = null, $headers = null);
    public function status();
    public function isMessageRejectable($message): bool;
    public function rejectMessage($message): void;
    public function acknowledgeMessage(AMQPMessage $message);
    public function requeueNewMessage($message, $queue = false);
    public function consumeFromConsumerQueue(callable $callback);
}
