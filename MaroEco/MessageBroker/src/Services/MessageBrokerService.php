<?php

namespace MaroEco\MessageBroker\Services;

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
     * @return void
     */
    public function connect()
    {
        $this->serviceRepo->connect();
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
                    $result = (bool) call_user_func($callback, $message->getBody());
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
    public function publishToQueue($msg, $queue = null, $headers = null)
    {
        $this->serviceRepo->publishMessageToQueue($msg, $queue, $headers);
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
