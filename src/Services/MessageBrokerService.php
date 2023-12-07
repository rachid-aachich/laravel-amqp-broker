<?php

namespace MaroEco\MessageBroker\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
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
    protected const LOG_CHANNELS = ['rabbitmq', 'single', 'stdout'];
    protected const ERR_CHANNELS = ['rabbitmq', 'stderr'];

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
     * The callback should return false if not succeeded or throw an exception.
     * If the callback doesn't succeed the message is requeued X times and finally sent to failureExchange
     *
     * @param string $consumeQueue
     * @param string $failureExchange
     * @param callable $callback
     */
    public function consumeMessage($consumeQueue, $failureExchange, callable $callback)
    {
        $handler = function($message) use($callback, $failureExchange, $consumeQueue) {
            if( !$this->amqpMessageService->validateMessage($message, $failureExchange) ) {
                return;
            }

            $pool = Pool::create();

            $pool->add(function () use ($message, $callback, $consumeQueue) {
                try {
                    $result = (bool) call_user_func($callback, $message);
                }
                catch (\Exception $e) {
                    Log::stack(self::ERR_CHANNELS)
                    ->error(
                        "Could not process message function : "
                        . $e->getMessage()
                    );
                }
                
                if( $result ) {
                    $this->amqpMessageService->takeMessage($message);
                } else {
                    Log::stack(self::LOG_CHANNELS)->error("Sending message to failureExchange");
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
    public function publishToExchange(
        $messageContent,
        string $exchangeName,
        array $headers = [],
        string $routingKey = ""
    )
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
