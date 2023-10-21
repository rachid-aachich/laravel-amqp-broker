<?php

namespace MaroEco\MessageBroker\Services;

use Illuminate\Support\Collection;
use MaroEco\MessageBroker\Contracts\MessageBrokerInterface;
use MaroEco\MessageBroker\Contracts\BrokerRepoInterface;
use Spatie\Async\Pool;
use Illuminate\Support\Facades\Log;

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
     * The broker repository instance.
     *
     * @var BrokerRepoInterface
     */
    protected BrokerRepoInterface $serviceRepo;

    /**
     * Create a new MessageBrokerService instance.
     *
     * @param BrokerRepoInterface $serviceRepo
     * @return void
     */
    public function __construct(BrokerRepoInterface $serviceRepo)
    {
        $this->serviceRepo = $serviceRepo;
    }

    /**
     * Establish a connection to the message broker.
     *
     * @param  mixed $queuesName An array of queue names to declare
     * @param  mixed $exchangesName An array of exchange names to declare.
     * @param  mixed $bindExchangeQueues An associative array where keys are exchange names, and values are arrays of queue names.
     * @return void
     */
    public function connect(array $queuesName = [], array $exchangesName = [], array $bindExchangeQueues = [])
    {
        $this->serviceRepo->connect($queuesName, $exchangesName, $bindExchangeQueues);
    }


    /**
     * Consume a message from a specific queue.
     *
     * @param callable The callback function to handle the consumed message
     * @return void
     */
    public function consumeMessage(callable $callback)
    {
        $handler = function($message) use($callback) {
            if( $this->serviceRepo->isMessageRejectable($message) ) {
                $this->serviceRepo->rejectMessage($message);
            } else {
                $pool = Pool::create();

                $pool->add(function () use ($message, $callback) {
                    $result = (bool) call_user_func($callback, $message);
                    if( $result ) {
                        $this->serviceRepo->acknowledgeMessage($message);
                    } else {
                        $this->serviceRepo->requeueNewMessage($message);
                    }
                });

                $pool->wait();
            }
        };

        $this->serviceRepo->consumeFromConsumerQueue($handler);
    }

    /**
     * Publish a message to a specific queue.
     *
     * @param mixed $msg The message to publish.
     * @param string|null $queue The name of the queue.
     * @param array|null $headers Additional headers for the message.
     * @return void
     */
    public function publishToQueue($msg, $headers = null, $queue = null)
    {
        $pool = Pool::create();

        $pool->add(function () use ($msg, $queue, $headers) {
            $this->serviceRepo->publishMessageToQueue($msg, $queue, $headers);
        });

        $pool->wait();

    }

    /**
     * Publish Bulk messages to a specific queue.
     *
     * @param Collection $messages The messages collection to publish.
     * @param array|null $headers Additional headers for the message.
     * @param string|null $exchange The name of the exchange.
     * @param string|null $queue The name of the queue.
     * @return void
     */
    public function publishBulkToQueue(Collection $messages, $headers = null, $exchange = null ,$queue = null)
    {
        $pool = Pool::create();

        $pool->add(function () use ($messages, $queue, $headers, $exchange) {
            $this->serviceRepo->publishBulkMessagesToQueue($messages, $headers, $exchange,$queue);
        });

        $pool->wait();

    }


    public function publishToExchange($msg, $headers = null, $exchangeName = null, $exchangeType = 'fanout')
    {
        $pool = Pool::create();

        $pool->add(function () use ($msg, $exchangeName, $exchangeType, $headers) {
            $this->serviceRepo->publishMessageToExchange($msg, $exchangeName, $exchangeType, $headers);
        });

        $pool->wait();

    }

    /**
     * Get the status of the message broker.
     *
     * @return array The status of the message broker.
     */
    public function getStatus()
    {
        return $this->serviceRepo->status();
    }

}
