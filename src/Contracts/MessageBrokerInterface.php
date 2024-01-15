<?php

namespace MaroEco\MessageBroker\Contracts;

use Illuminate\Support\Collection;
use PhpAmqpLib\Message\AMQPMessage;

interface MessageBrokerInterface
{

    public function connect();

    /**
     * Consume message.
     *
     * @param callable The callback function to handle the consumed message
     * @return void
     */
    public function consumeMessage(string $consumeQueue, callable $callback);

    /**
     * Publish a message to a specific queue.
     *
     * @param mixed $msg The message to publish.
     * @param string|null $queue The name of the queue.
     * @param array|null $headers Additional headers for the message.
     * @return void
     */
    public function publishToQueue($messageContent, string $queue , array $headers = []);

    /**
     * Publish Bulk messages to a specific queue.
     *
     * @param Collection $messages The messages collection to publish.
     * @param array|null $headers Additional headers for the message.
     * @param string|null $exchange The name of the exchange.
     * @param string|null $queue The name of the queue.
     * @return void
     */
    public function publishBulkMessagesToQueue(Collection $messages, string $queue, array $headers = []);

    /**
     * Publish Bulk messages to a specific queue.
     *
     * @param Collection $messages The messages collection to publish.
     * @param array|null $headers Additional headers for the message.
     * @param string|null $exchange The name of the exchange.
     * @param string|null $queue The name of the queue.
     * @return void
     */
    public function publishBulkMessagesToExchange(Collection $messages, string $exchange, array $headers = []);

    /**
     * Publish a message to a specific exchange.
     *
     * @param mixed $messageContent The message to publish.
     * @param string $exchangeName The name of the exchange.
     * @param array $headers Additional headers for the message.
     * @param string $routingKey The Routing key for the exchange.
     * @return void
     */
    public function publishToExchange($messageContent, string $exchangeName, array $headers = [], string $routingKey = "");
    
    /**
     * Get the status of the message broker.
     *
     * @return array The status of the message broker.
     */
    public function getStatus();
}
