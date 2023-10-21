<?php

namespace MaroEco\MessageBroker\Contracts;

interface MessageBrokerInterface
{
    public function connect();
    public function consumeMessage(callable $callback);
    public function publishToQueue($msg, $queue = null, $headers = null);
    public function getStatus();
}
