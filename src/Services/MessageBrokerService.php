<?php

namespace MaroEco\MessageBroker\Services;

use Illuminate\Support\Collection;
use MaroEco\MessageBroker\Contracts\MessageBrokerInterface;
use Spatie\Async\Pool;
use MaroEco\MessageBroker\Contracts\AMQPMessageServiceInterface;

/**
 * A service class implementing the MessageBrokerInterface.
 *
 * This class provides methods for establishing a connection to a message broker,
 * consuming messages from a queue, publishing messages to a queue, and retrieving
 * the status of the message broker.
 */
class MessageBrokerService implements MessageBrokerInterface
{
    /**
     * Create a new MessageBrokerService instance.
     *
     */
    public function __construct(private AMQPMessageServiceInterface $amqpMessageService) {}

    /**
     * Establish a connection to the message broker.
     */
    public function connect()
    {
        $this->amqpMessageService->connect();
    }

    /**
     * Consume a message from the specified queue and handle it using the provided callback.
     *
     * @param string $consumeQueue
     * @param string $rejectQueue
     * @param callable $callback
     */
    public function consumeMessage($consumeQueue, $rejectQueue, callable $callback)
    {
        $handler = function($message) use($callback, $rejectQueue, $consumeQueue) {
            if( !$this->amqpMessageService->validateMessage($message, $rejectQueue) ) return;

            $pool = Pool::create();

            $pool->add(function () use ($message, $callback, $consumeQueue) {
                $result = (bool) call_user_func($callback, $message);
                if( $result ) {
                    $this->amqpMessageService->takeMessage($message);
                } else {
                    $this->amqpMessageService->requeueNewMessage($message, $consumeQueue);
                }
            });

            $pool->wait();
        };

        $this->amqpMessageService->consumeMessageFromQueue($consumeQueue, $handler);
    }

    /**
     * Publish a message to the specified queue.
     *
     * @param mixed $messageContent
     * @param string $queue
     * @param array $headers
     */
    public function publishToQueue($messageContent, string $queue , array $headers = [])
    {
        $pool = Pool::create();

        $pool->add(function () use ($messageContent, $queue, $headers) {
            $this->amqpMessageService->publishMessageToQueue($messageContent, $queue , $headers);
        });

        $pool->wait();
    }

    /**
     * Publish a message to the specified exchange.
     *
     * @param mixed $messageContent
     * @param string $exchangeName
     * @param array $headers
     * @param string $routingKey
     */
    public function publishToExchange($messageContent, string $exchangeName , array $headers = [], string $routingKey = "")
    {
        $pool = Pool::create();

        $pool->add(function () use ($messageContent, $exchangeName, $headers, $routingKey) {
            $this->amqpMessageService->publishMessageToExchange($messageContent, $exchangeName, $headers, $routingKey);
        });

        $pool->wait();

    }

    /**
     * Publish a collection of messages to the specified exchange.
     *
     * @param Collection $messages
     * @param string $exchange
     * @param array $headers
     */
    public function publishBulkMessagesToExchange(Collection $messages, string $exchange, array $headers = [])
    {
        $pool = Pool::create();

        $pool->add(function () use ($messages, $exchange, $headers) {
            $this->amqpMessageService->publishBulkMessagesToExchange($messages, $exchange, $headers);
        });

        $pool->wait();

    }

    /**
     * Publish a collection of messages to the specified queue.
     *
     * @param Collection $messages
     * @param string $queue
     * @param array $headers
     */
    public function publishBulkMessagesToQueue(Collection $messages, string $queue, array $headers = [])
    {
        $pool = Pool::create();

        $pool->add(function () use ($messages, $queue, $headers) {
            $this->amqpMessageService->publishBulkMessagesToQueue($messages, $queue, $headers);
        });

        $pool->wait();

    }

    /**
     * Get the status of the message broker Connection.
     *
     * @return mixed
     */
    public function getStatus()
    {
        return $this->amqpMessageService->getStatus();
    }
}
