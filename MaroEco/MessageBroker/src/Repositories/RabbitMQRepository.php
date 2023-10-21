<?php

namespace MaroEco\MessageBroker\Repositories;

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
    public function connect()
    {
        Log::stack(self::LOG_CHANNELS)->info('start connect!');
        try {
            retry($this->maxRetries, function () {
                self::$connection = new AMQPStreamConnection(
                    config('messagebroker.rabbitmq.host'),
                    config('messagebroker.rabbitmq.port'),
                    config('messagebroker.rabbitmq.user'),
                    config('messagebroker.rabbitmq.password'),
                    config('messagebroker.rabbitmq.vhost')
                );
                self::$channel = self::$connection->channel();
                // Connection still failed after retries
                Log::stack(self::LOG_CHANNELS)->info(
                    "Succeeded in establishing connection with RabbitMQ server."
                );

            }, $this->retryDelay);
        } catch (AMQPConnectionClosedException $e) {
            Log::stack(self::LOG_CHANNELS)->error(
                "connect To RMQStream Connection Closed".
                " from connectToRMQStream : " .
                $e->getMessage()
            );
        } catch (AMQPRuntimeException $e) {
            Log::stack(self::LOG_CHANNELS)->error(
                'connect To RMQStream Runtime Exception: ' .
                    $e->getMessage()
                );
        } catch (\Exception $e) {
            Log::stack(self::LOG_CHANNELS)->error(
                "connect To RMQStream \n -class : ".
                get_class($e).
                "\n -Exception : " .
                $e->getMessage()
            );
        }
        if (self::$connection === null) {
            // Connection still failed after retries
            Log::stack(self::LOG_CHANNELS)->error(
                "Failed to establish connection with RabbitMQ server after many retries."
            );
            // send notification email to the Admin Manager
            //
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
        $headers = $message->get('application_headers')->getNativeData();
        $headers['x-delivery-attempts'] = (int) (
            $message->get('application_headers')
            ->getNativeData()['x-delivery-attempts'] ?? 0
            ) + 1;
        Log::stack(self::LOG_CHANNELS)->info('the message is : ', [$message]);
        Log::stack(self::LOG_CHANNELS)->info('the headers are : ', [$headers]);
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
            'application_headers' => $this->getRMQHeadersfromArray($headers)
        ]);
    }

    /**
     * Converts an array of headers into an AMQPTable.
     *
     * @param array|null $headers
     * @return AMQPTable
     */
    private function getRMQHeadersfromArray($headers = null)
    {
        return new AMQPTable(is_array($headers) ? $headers : []);
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
        $deliveryAttempts = $message->get('application_headers')
        ->getNativeData()['x-delivery-attempts'] ?? 0;

        return $deliveryAttempts > $this->maxDeliveryLimit;
    }

    public function isMessageRejectable($message): bool
    {
        return empty($message) || !$message || $this->hasExceededDeliveryLimit($message);
    }

    public function rejectMessage($message): void
    {
        self::$channel->queue_declare($this->rejectQueue, false, true, false, false);
        $this->publishMessageToQueue($message, $this->rejectQueue);
        $this->acknowledgeMessage($message);
    }

    /**
     * Requeues a new message and removes the old one.
     *
     * @param AMQPMessage $msg
     * @param string $queue
     * @return void
     */
    public function requeueNewMessage($message, $queue = false)
    {
        $newMessage = $this->getNewMessageIncrementHeaders($message);
        $queue = $queue ?? $this->consumeQueue;

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

            self::$channel->queue_declare($queue, false, true, false, false);

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
            // Todo later: send email to the Admin Manager
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
    public function publishMessageToQueue($msg, $queue = null, $headers = null)
    {
        try {
            if (!self::$connection || !self::$connection->isConnected()) {
                throw new AMQPConnectionClosedException(
                    'Failed to establish connection to RabbitMQ server.'
                );
            }

            $msg = $queue ? $msg->getBody() : json_encode($msg);

            $queue = $queue ?: $this->publishQueue;
            self::$channel->queue_declare($queue, false, true, false, false);

            $newMessage = new AMQPMessage($msg, [
                'application_headers' => $this->getRMQHeadersfromArray($headers)
            ]);

            self::$channel->basic_publish($newMessage, '', $queue);
            Log::stack(self::LOG_CHANNELS)->info(" [x] Publish new Message To $queue ", [$msg]);
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
        !isset(self::$connection) ? self::$connection->close() : self::$connection = null;
        !isset(self::$channel) ? self::$channel->close() : self::$channel = null;
    }
}
