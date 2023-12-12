<?php

namespace MaroEco\MessageBroker\Contracts;

use PhpAmqpLib\Message\AMQPMessage;

interface AMQPHelperServiceInterface
{
    public function createAMQPTableFromArray($headers = null);

    public function getHeadersFromAMQPMessage(AMQPMessage $message) : array;

    public function hasExceededDeliveryLimit(AMQPMessage $message);

    public function isMessageRejectable($message): bool;

    public function isAMQPMessage($message): bool;

    public function createPersistenceAMQPMessage($content, array $headers = []) : AMQPMessage;
}
