<?php

namespace MaroEco\MessageBroker\Services;

use Fiber;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use MaroEco\MessageBroker\Contracts\MessageBrokerInterface;
use Spatie\Async\Pool;
use MaroEco\MessageBroker\Contracts\AMQPMessageServiceInterface;
use Psr\Log\LoggerInterface;

/**
 * A service class implementing the MessageBrokerInterface.
 *
 * This class provides methods for establishing a connection to a message broker,
 * consuming messages from a queue, publishing messages to a queue, and retrieving
 * the status of the message broker.
 */
class MessageBrokerService implements MessageBrokerInterface
{

    protected LoggerInterface $logger;

    /**
     * Create a new MessageBrokerService instance.
     *
     */
    public function __construct(private AMQPMessageServiceInterface $amqpMessageService, LoggerInterface $logger) {
        $this->logger = $logger;
    }

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
     * If the callback doesn't succeed the message is requeued X times and finally nack'ed
     *
     * @param string $consumeQueue
     * @param callable $callback
     */
    public function consumeMessage($consumeQueue, callable $callback)
    {
        $handler = function($message) use($callback) {
            if( !$this->amqpMessageService->validateMessage($message) ) return;

            $fiber = new Fiber(function () use ($message, $callback) {
                try
                {
                    $result = (bool) call_user_func($callback, $message);
                }
                catch (\Exception $e) {
                    $this->logger->error(
                        "Could not process message function : "
                        . $e->getMessage()
                    );
                }

                if( $result ) {
                    $this->amqpMessageService->takeMessage($message);
                } else {
                    $this->amqpMessageService->rejectMessage($message);
                }
            });

            $fiber->start();

            // Wait for the Fiber to finish
            while ($fiber->isStarted() && !$fiber->isTerminated()) {
                $fiber->resume();
            }

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
        $fiber = new Fiber(function () use ($messageContent, $queue, $headers) {
            $this->amqpMessageService->publishMessageToQueue($messageContent, $queue, $headers);
        });

        // Start the Fiber
        $fiber->start();

        // Wait for the Fiber to finish
        while ($fiber->isStarted() && !$fiber->isTerminated()) {
            $fiber->resume();
        }
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
        $fiber = new Fiber(function () use ($messageContent, $exchangeName, $headers, $routingKey) {
            $this->amqpMessageService->publishMessageToExchange($messageContent, $exchangeName, $headers, $routingKey);
        });

        $fiber->start();

        // Wait for the Fiber to finish
        while ($fiber->isStarted() && !$fiber->isTerminated()) {
            $fiber->resume();
        }

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
        $fiber = new Fiber(function () use ($messages, $exchange, $headers) {
            $this->amqpMessageService->publishBulkMessagesToExchange($messages, $exchange, $headers);
        });

        $fiber->start();

        // Wait for the Fiber to finish
        while ($fiber->isStarted() && !$fiber->isTerminated()) {
            $fiber->resume();
        }

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
        $fiber = new Fiber(function () use ($messages, $queue, $headers) {
            $this->amqpMessageService->publishBulkMessagesToQueue($messages, $queue, $headers);
        });

        $fiber->start();

        // Wait for the Fiber to finish
        while ($fiber->isStarted() && !$fiber->isTerminated()) {
            $fiber->resume();
        }
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
