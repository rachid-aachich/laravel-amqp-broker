<?php

namespace MaroEco\MessageBroker\Contracts;

use Illuminate\Support\Collection;

interface MessageBrokerInterface
{
    public function connect(array $queuesName = [], array $exchangesName = [], array $bindExchangeQueues = []);
    public function consumeMessage(callable $callback);
    public function publishToQueue($msg, $headers = null, $queue = null);
    public function publishBulkToQueue(Collection $messages, $headers = null, $exchange = null ,$queue = null);
    public function publishToExchange($msg, $headers = null, $exchangeName = null, $exchangeType = 'fanout');
    public function getStatus();
}
