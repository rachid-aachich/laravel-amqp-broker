<?php

namespace MaroEco\MessageBroker\Services;

use Illuminate\Support\Collection;
use MaroEco\MessageBroker\Contracts\AMQPHelperServiceInterface;
use MaroEco\MessageBroker\Contracts\AMQPMessageServiceInterface;
use MaroEco\MessageBroker\Contracts\BrokerRepoInterface;
use PhpAmqpLib\Message\AMQPMessage;

class AMQPMessageService implements AMQPMessageServiceInterface
{
    protected const LOG_CHANNELS = ['rabbitmq', 'single', 'stderr'];

    public function __construct(
        private AMQPHelperServiceInterface $amqpHelperService,
        private BrokerRepoInterface $messageBrokerRepository)
    {}

    /**
     * Establish a connection to the message broker.
     */
    public function connect()
    {
        $this->messageBrokerRepository->connect();
    }

    /**
     * Validate a message and determine if it should be rejected.
     *
     * @param AMQPMessage $message
     * @param string $failureExchange
     * @param array $headers
     * @return bool True if the message is valid, false if it should be rejected.
     */
    public function validateMessage(AMQPMessage $message, string $failureExchange, array $headers = []): bool
    {
        if (!$this->amqpHelperService->isMessageRejectable($message)) return true; // this message is valid

        // this message should be rejected
        $headers = $this->amqpHelperService->getHeadersFromAMQPMessage($message);
        if (array_key_exists('x-delivery-attempts', $headers)) {
            unset($headers['x-delivery-attempts']);
        }

        $amqpMessage = $this->amqpHelperService->createPersistenceAMQPMessage($message->getBody(), $headers);

        $this->publishMessageToExchange($amqpMessage, $failureExchange, $headers);

        $this->takeMessage($message);

        return false;
    }

    /**
     * Acknowledge and remove a message from the queue.
     *
     * @param AMQPMessage $message
     */
    public function takeMessage($message)
    {
        $this->messageBrokerRepository->acknowledgeMessage($message);
    }

    /**
     * Requeues a new message and removes the old one.
     *
     * @param AMQPMessage $msg
     * @param string $queue
     * @return void
     */
    public function requeueNewMessage(AMQPMessage $message, string $queue)
    {
        $newMessage = $this->amqpHelperService->getNewMessageIncrementHeaders($message);

        $this->publishMessageToQueue($newMessage, $queue);

        // Aknowledge the message re-delivery and thereafter removing its old instance from the queue
        $this->takeMessage($message);
    }

    /**
     * Consume a message from the specified queue and handle it using the provided callback.
     *
     * @param string $consumeQueue
     * @param callable $callback
     */
    public function consumeMessageFromQueue(string $consumeQueue, callable $callback): void
    {
        $this->messageBrokerRepository->consumeMessageFromQueue($consumeQueue, $callback);
    }

    /**
     * Publish a message to the specified queue.
     *
     * @param mixed $message
     * @param string $queue
     * @param array $headers
     */
    public function publishMessageToQueue($message, string $queue, array $headers = [])
    {
        $amqpMessage = $this->amqpHelperService->createPersistenceAMQPMessage($message, $headers);
        $this->messageBrokerRepository->publishMessageToQueue($amqpMessage, $queue);
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
        $messages = $messages->map(function ($msg) use($headers){
            return $this->amqpHelperService->createPersistenceAMQPMessage($msg, $headers);
        });
        $this->messageBrokerRepository->publishBulkMessagesToQueue($messages, $queue);
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
        $messages = $messages->map(function ($msg) use($headers){
            return $this->amqpHelperService->createPersistenceAMQPMessage($msg, $headers);
        });
        $this->messageBrokerRepository->publishBulkMessagesToExchange($messages, $exchange);
    }

    /**
     * Publish a message to the specified exchange.
     *
     * @param mixed $message
     * @param string $exchangeName
     * @param array $headers
     * @param string $routingKey
     */
    public function publishMessageToExchange($message, string $exchangeName, array $headers = [], string $routingKey = "")
    {
        $amqpMessage = $this->amqpHelperService->createPersistenceAMQPMessage($message, $headers, $routingKey);
        $this->messageBrokerRepository->publishMessageToExchange($amqpMessage, $exchangeName, $routingKey);
    }

    /**
     * Get the status of the message broker Connection.
     *
     * @return array The status of the message broker.
     */
    public function getStatus()
    {
        return $this->messageBrokerRepository->status();
    }
}
