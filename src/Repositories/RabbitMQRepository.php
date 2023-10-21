<?php

namespace MaroEco\MessageBroker\Repositories;

use Illuminate\Support\Collection;
use MaroEco\MessageBroker\Contracts\BrokerRepoInterface;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

use function config;

class RabbitMQRepository implements BrokerRepoInterface
{
    private static $channel;
    private static $connection;

    protected $consumeQueue;
    protected $rejectQueue;
    protected $maxRetries;
    protected $retryDelay;
    protected $maxDeliveryLimit;
    protected $publishQueue;

    protected const LOG_CHANNELS = ['rabbitmq', 'single', 'stderr'];

    public function __construct()
    {
        $this->consumeQueue  = config('messagebroker.rabbitmq.queue_consumer');
        $this->rejectQueue  = config('messagebroker.rabbitmq.reject_queue');
        $this->maxRetries   = config('messagebroker.rabbitmq.maxRMQConnectionRetries');
        $this->retryDelay   = config('messagebroker.rabbitmq.maxRMQConnectionRetryDelay'); // Delay in milliseconds
        $this->maxDeliveryLimit = config('messagebroker.rabbitmq.maxRMQDeliveryLimit');
        $this->publishQueue = config('messagebroker.rabbitmq.publish_queue');
    }

    /**
     * Establishes a connection to the RabbitMQ server.
     *
     * @return void
     */
    public function connect(array $queuesName = [], array $exchangesName = [], array $bindExchangeQueues = [])
    {
        Log::stack(self::LOG_CHANNELS)->info('start connect!');
        try {
            retry($this->maxRetries, function () use($queuesName, $exchangesName, $bindExchangeQueues) {
                self::$connection = new AMQPStreamConnection(
                    config('messagebroker.rabbitmq.hostname'),
                    config('messagebroker.rabbitmq.port'),
                    config('messagebroker.rabbitmq.username'),
                    config('messagebroker.rabbitmq.password'),
                    config('messagebroker.rabbitmq.vhost')
                );
                self::$channel = self::$connection->channel();
                $this->setupQueuesAndExchanges($queuesName, $exchangesName, $bindExchangeQueues);

                Log::stack(self::LOG_CHANNELS)->info("Succeeded in establishing connection with RabbitMQ server.");
            }, $this->retryDelay);
        } catch (AMQPConnectionClosedException | AMQPRuntimeException | \Exception $e) {
            // Close the connection and channel if they are set

            Log::stack(self::LOG_CHANNELS)->error("connect To RMQStream \n -class : " .
                get_class($e) .
                "\n -Exception : " .
                $e->getMessage());

            $this->closeConnection();
            // Rethrow the exception to ensure that it's handled further up the call stack
            throw $e;
        }
    }


    /**
     * Increments the delivery attempts for a given message.
     *
     * @param AMQPMessage $message
     * @return array
     */
    private function incrementDeliveryAttempts($message)
    {
        $headers = $this->getHeadersFromAMQPMessage($message);
        $headers['x-delivery-attempts'] = (int) (
            $headers['x-delivery-attempts'] ?? 0
            ) + 1;
        return $headers;
    }

    /**
     * Generates a new message with incremented headers.
     *
     * @param AMQPMessage $message
     * @return AMQPMessage
     */
    private function getNewMessageIncrementHeaders($message)
    {
        $headers = $this->incrementDeliveryAttempts($message);
        // Create a new AMQPMessage with the updated headers
        return new AMQPMessage($message->getBody(), [
            'application_headers' => $this->createAMQPTableFromArray($headers)
        ]);
    }

    /**
     * Converts an array of headers into an AMQPTable.
     *
     * @param array|null $headers
     * @return AMQPTable
     */
    private function createAMQPTableFromArray($headers = null)
    {
        return new AMQPTable(is_array($headers) ? $headers : []);
    }

    /**
     * Get headers from an AMQP message.
     *
     * @param \PhpAmqpLib\Message\AMQPMessage $message The AMQP message containing headers.
     *
     * @return array An associative array containing the application headers.
     */
    private function getHeadersFromAMQPMessage(AMQPMessage $message) : array
    {
        return $message->get('application_headers')->getNativeData();
    }

    public function acknowledgeMessage(AMQPMessage $message)
    {
        self::$channel->basic_ack($message->delivery_info['delivery_tag']);
    }

    /**
     * Checks if the delivery limit has been exceeded for a given message.
     *
     * @param AMQPMessage $message
     * @return bool
     */
    private function hasExceededDeliveryLimit(AMQPMessage $message)
    {
        $deliveryAttempts = $this->getHeadersFromAMQPMessage($message)['x-delivery-attempts'] ?? 0;

        return $deliveryAttempts > $this->maxDeliveryLimit;
    }

    public function isMessageRejectable($message): bool
    {
        return empty($message) || !$message || $this->hasExceededDeliveryLimit($message);
    }

    public function rejectMessage($message): void
    {
        $this->publishMessageToQueue($message, $this->rejectQueue);
        $this->acknowledgeMessage($message);
    }

    /**
     * Check if a variable is an instance of an AMQPMessage.
     *
     * @param mixed $message The variable to check.
     *
     * @return bool True if the variable is an instance of AMQPMessage, false otherwise.
     */
    public function isAMQPMessage($message): bool
    {
        return $message instanceof AMQPMessage;
    }

    /**
     * Requeues a new message and removes the old one.
     *
     * @param AMQPMessage $msg
     * @param string $queue
     * @return void
     */
    public function requeueNewMessage($message, $queue = null)
    {
        $newMessage = $this->getNewMessageIncrementHeaders($message);
        $queue ??= $this->consumeQueue;

        // Publish the new message to the queue with the updated headers
        self::$channel->basic_publish($newMessage, '', $queue);

        // Aknowledge the message re-delivery and thereafter removing its old instance from the queue
        $this->acknowledgeMessage($message);
    }

    public function consumeFromConsumerQueue(callable $callback): void
    {
        $this->consumeMessageFromQueue($this->consumeQueue, $callback);
    }

    /**
     * Consumes a message from a given queue.
     *
     * @param string|null $queue
     * @param callable $callback
     * @return void
     */
    public function consumeMessageFromQueue($queue, callable $callback): void
    {
        try {
            Log::stack(self::LOG_CHANNELS)->info('consumption started!');
            if (self::$connection == null || !self::$connection->isConnected()) {
                throw new AMQPConnectionClosedException('Failed to establish connection to RabbitMQ server.');
            }

            // Check if the channel is still open, if not, recreate it
            if (self::$channel == null || !self::$channel->is_open()) {
                self::$channel = self::$connection->channel();
            }

            self::$channel->basic_consume($queue, '', false, false, false, false, $callback);

            while (count(self::$channel->callbacks)) {
                self::$channel->wait();
            }

        } catch (AMQPConnectionClosedException $e) {
            Log::stack(self::LOG_CHANNELS)
                ->error(
                    "connect To RMQStream Connection Closed from consumeMessageFromQueue function : "
                    . $e->getMessage()
                );
            // To enhanced later: report error
        } catch (\Exception $e) {
            Log::stack(self::LOG_CHANNELS)
                ->error(
                    "The Consuming is not completed from consumeMessageFromQueue function"
                    . "\n Exception: "
                    . $e->getMessage()
                    . "\n Stacktrace: "
                    . $e->getTraceAsString()
                );
        }
    }


    /**
     * Publishes a message to a given queue.
     *
     * @param mixed $msg
     * @param string|null $queue
     * @param array|null $headers
     * @return void
     */
    public function publishMessageToQueue($msg, string|null $queue = null, array|null $headers = null)
    {
        try {
            if (!self::$connection || !self::$connection->isConnected()) {
                throw new AMQPConnectionClosedException(
                    'Failed to establish connection to RabbitMQ server.'
                );
            }

            $queue ??= $this->publishQueue;

            if ($queue && $queue != $this->publishQueue)
            {
                $this->declareQueue($queue);
            }

            $msgIsAMQPMessage = $this->isAMQPMessage($msg);

            $message = $msgIsAMQPMessage ? $msg->getBody() : json_encode($msg);
            $headers ??= $msgIsAMQPMessage ? $this->getHeadersFromAMQPMessage($msg) : [];

            $newMessage = new AMQPMessage($message, [
                'application_headers' => $this->createAMQPTableFromArray($headers)
            ]);

            self::$channel->basic_publish($newMessage, '', $queue);
        } catch (AMQPConnectionClosedException $e) {
            Log::stack(self::LOG_CHANNELS)->error(
                "connect To RMQStream Connection Closed "
                . "from publishToQueue function."
                . "\n Exception: "
                . $e->getMessage()
                . "\n Stacktrace: "
                . $e->getTraceAsString()
            );
            // send email to the Admin Manager
            //
        } catch (\Exception $e) {
            Log::stack(self::LOG_CHANNELS)->warning(
                "The Publish is not completed \n"
                . "from publishToQueue function: \n"
                . "\n Exception: "
                . $e->getMessage()
                . "\n Stacktrace: "
                . $e->getTraceAsString()
                . "\n- Payload: ",
                [$msg->body]
            );
        }
    }


    /**
     * Publishes a Bulk of messages (Laravel Collection Of data) to a given queue.
     *
     * @param Collection $messages
     * @param string|null $exchange the name of the exchange to bind with the queue
     * @param string|null $queue
     * @param array|null $headers
     * @return void
     */
    public function publishBulkMessagesToQueue(Collection $messages, $headers = null, $exchange = null ,$queue = null)
    {
        try {
            if (!self::$connection || !self::$connection->isConnected()) {
                throw new AMQPConnectionClosedException(
                    'Failed to establish connection to RabbitMQ server.'
                );
            }
            $exchange ??= env("RABBITMQ_REPUBLISH_EXCHANGE");

            if ($queue && $queue != $this->publishQueue)
            {
                $this->declareQueue($queue);
            } else {
                $queue = $this->publishQueue;
            }

            self::$channel->confirm_select();

            $this->declareExchange($exchange, 'direct');
            $this->bindQueuesWithExchange($queue, $exchange);

            $messages->map(function ($msg) use($headers, $exchange){
                $newMessage = new AMQPMessage($msg, [
                    'application_headers' => $this->createAMQPTableFromArray($headers)
                ]);
                self::$channel->batch_basic_publish($newMessage, $exchange,'');
            });

            self::$channel->publish_batch();
            self::$channel->wait_for_pending_acks_returns();

        } catch (AMQPConnectionClosedException $e) {
            Log::stack(self::LOG_CHANNELS)->error(
                "connect To RMQStream Connection Closed "
                . "from publishToQueue function."
                . "\n Exception: "
                . $e->getMessage()
                . "\n Stacktrace: "
                . $e->getTraceAsString()
            );
            // send email to the Admin Manager
            //
        } catch (\Exception $e) {
            Log::stack(self::LOG_CHANNELS)->warning(
                "The Publish is not completed \n"
                . "from publishToQueue function: \n"
                . "\n Exception: "
                . $e->getMessage()
                . "\n Stacktrace: "
                . $e->getTraceAsString()
                . "\n- Payload: ",
                [$messages]
            );
        }
    }


    public function publishMessageToExchange($msg, string|null $exchangeName = null, $exchangeType = 'fanout', array|null $headers = null)
    {
        try {
            if (!self::$connection || !self::$connection->isConnected()) {
                throw new AMQPConnectionClosedException(
                    'Failed to establish connection to RabbitMQ server.'
                );
            }

            $this->declareExchange($exchangeName,$exchangeType,true);


            $msgIsAMQPMessage = $this->isAMQPMessage($msg);

            $message = $msgIsAMQPMessage ? $msg->getBody() : json_encode($msg);
            $headers ??= $msgIsAMQPMessage ? $this->getHeadersFromAMQPMessage($msg) : [];

            $newMessage = new AMQPMessage($message, [
                'application_headers' => $this->createAMQPTableFromArray($headers)
            ]);

            self::$channel->basic_publish($newMessage, $exchangeName);
        } catch (AMQPConnectionClosedException $e) {
            Log::stack(self::LOG_CHANNELS)->error(
                "connect To RMQStream Connection Closed "
                . "from publishToQueue function."
                . "\n Exception: "
                . $e->getMessage()
                . "\n Stacktrace: "
                . $e->getTraceAsString()
            );
            // send email to the Admin Manager
            //
        } catch (\Exception $e) {
            Log::stack(self::LOG_CHANNELS)->warning(
                "The exchange Publish is not completed \n"
                . "from publishToQueue function: \n"
                . "\n Exception: "
                . $e->getMessage()
                . "\n Stacktrace: "
                . $e->getTraceAsString()
                . "\n- Payload: ",
                [$msg->body]
            );
        }
    }

    /**
     * Set up RabbitMQ queues, exchanges, and bindings based on provided configuration.
     *
     * @param  array $queuesName An array of queue names to declare
     * @param  array $exchangesName An array of exchange names to declare
     * @param  array $bindExchangeQueues An associative array where keys are exchange names, and values are arrays of queue names.
     * @return void
     */
    public function setupQueuesAndExchanges(array $queuesName = [], array $exchangesName = [], array $bindExchangeQueues = [])
    {
        $queuesName = empty($queuesName) ? config("messagebroker.defaultQueues") : $queuesName;
        foreach ($queuesName as $queueName)
        {
            $this->declareQueue($queueName);
        }

        foreach ($exchangesName as $exchange)
        {
            $this->declareExchange($exchange);
        }

        foreach ($bindExchangeQueues as $exchange => $queues)
        {
            foreach ($queues as $queue)
            {
                $this->bindQueuesWithExchange($exchange , $queue);
            }
        }
    }


    /**
     * Declares a queue with optional parameters.
     *
     * @param  mixed $queueName
     * @param  mixed $checkQueueExists if is true, the server will not create the queue if it does not already exist.
     * @param  mixed $durable if is true, the queue will survive server restarts.
     * @param  mixed $exclusive if is true, the queue can only be accessed by the current connection and will be deleted when the connection is closed.
     * @param  mixed $autoDelete if is true, the queue will be deleted when there are no consumers left.
     * @return void
     */
    public function declareQueue(string $queueName,$checkQueueExists = false, $durable = true, $exclusive = false, $autoDelete = false )
    {
        self::$channel->queue_declare(
            $queueName, $checkQueueExists, $durable, $exclusive, $autoDelete
        );
    }

    /**
     * declare an Exchange
     *
     * @param  mixed $exchangeName The name of the exchange to declare.
     * @param  mixed $exchangeType The type of the exchange ('direct', 'topic', 'fanout','headers, etc.).
     * @param  mixed $checkQueueExists if is true, the server will not create the Exchange if it does not already exist.
     * @param  mixed $durable if is true, the Exchange will survive server restarts.
     * @param  mixed $autoDelete if is true, the Exchange will be deleted when there are no consumers left.
     *
     * @return void
     */
    public function declareExchange($exchangeName, $exchangeType = 'fanout', $checkQueueExists = false, $durable = true, $autoDelete = false)
    {
        self::$channel->exchange_declare($exchangeName, $exchangeType, $checkQueueExists, $durable, $autoDelete);
    }

    /**
     * Bind a queue to an exchange with a routing key
     *
     * @param  mixed $exchangeName The name of the exchange.
     * @param  mixed $queueName The name of the queue.
     * @param  mixed $routingKey The routing key to use for the binding , the default is without key.
     * @param  mixed $nowait If set to true, the server will not respond to the method.
     * @param  mixed $arguments Additional binding arguments.
     * @return void
     */
    public function bindQueuesWithExchange($exchangeName, $queueName , $routingKey = '', $nowait = false, $arguments = [])
    {
        self::$channel->queue_bind($queueName, $exchangeName, $routingKey, $nowait, $arguments);
    }

    /**
     * Returns the status of the RabbitMQ server.
     *
     * @return array
     */
    public function status()
    {
        return [
            "brokerName"    => "RabbitMQ",
            "connect"       => $this->isConnected(),
            "consuming"     => $this->isConsuming()
        ];
    }

    /**
     * Checks if the RabbitMQ server is connected.
     *
     * @return bool
     */
    private function isConnected()
    {
        try {
            return self::$connection && self::$connection->isConnected();
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * Checks if the RabbitMQ server is consuming messages.
     *
     * @return bool
     */
    private function isConsuming()
    {
        try {
            return self::$channel->is_consuming();
        } catch (\Throwable $th) {
            return false;
        }
    }

    /**
     * Closes the connection to the RabbitMQ server.
     *
     * @return void
     */
    public function closeConnection()
    {
        isset(self::$connection) ? self::$connection->close() : self::$connection = null;
        isset(self::$channel) ? self::$channel->close() : self::$channel = null;
    }

}
