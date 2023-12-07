<?php

namespace MaroEco\MessageBroker\Repositories;

use Illuminate\Support\Collection;
use MaroEco\MessageBroker\Contracts\BrokerRepoInterface;
use Illuminate\Support\Facades\Log;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Exception\AMQPConnectionClosedException;
use PhpAmqpLib\Exception\AMQPInvalidArgumentException;
use PhpAmqpLib\Exception\AMQPRuntimeException;
use PhpAmqpLib\Message\AMQPMessage;

use function config;

class RabbitMQRepository implements BrokerRepoInterface
{
    private static $channel;
    private static $connection;

    protected $maxRetries;
    protected $retryDelay;
    protected $maxDeliveryLimit;

    protected const LOG_CHANNELS = ['rabbitmq', 'single', 'stderr'];

    public function __construct()
    {
        $this->maxRetries   = config('messagebroker.rabbitmq.maxRMQConnectionRetries');
        $this->retryDelay   = config('messagebroker.rabbitmq.maxRMQConnectionRetryDelay'); // Delay in milliseconds
        $this->maxDeliveryLimit = config('messagebroker.rabbitmq.maxRMQDeliveryLimit');
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
                    config('messagebroker.rabbitmq.hostname'),
                    config('messagebroker.rabbitmq.port'),
                    config('messagebroker.rabbitmq.username'),
                    config('messagebroker.rabbitmq.password'),
                    config('messagebroker.rabbitmq.vhost')
                );
                self::$channel = self::$connection->channel();

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
     * Consumes a message from a given queue.
     *
     * @param string $consumeQueue
     * @param callable $callback
     * @return void
     * @throws AMQPConnectionClosedException
     */
    public function consumeMessageFromQueue(string $consumeQueue, callable $callback): void
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

            self::$channel->basic_consume($consumeQueue, '', false, false, false, false, $callback);

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
        } catch (AMQPInvalidArgumentException $e) {
            Log::stack(self::LOG_CHANNELS)
                ->error(
                    "Cannot consume from the queue: " . $consumeQueue . ". Will exit the process."
                    . "\n Exception: "
                    . $e->getMessage()
                    . "\n Stacktrace: "
                    . $e->getTraceAsString()
                );
        } catch (\Exception $e) {
            Log::stack(self::LOG_CHANNELS)
                ->error(
                    "Unrecoverable error occurred"
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
     * @param AMQPMessage $message
     * @param string $publishQueue
     * @return void
     * @throws AMQPConnectionClosedException
     */
    public function publishMessageToQueue(AMQPMessage $message, string $publishQueue)
    {
        try {
            if (!self::$connection || !self::$connection->isConnected()) {
                throw new AMQPConnectionClosedException(
                    'Failed to establish connection to RabbitMQ server.'
                );
            }

            self::$channel->basic_publish($message, '', $publishQueue);

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
                [$message->body]
            );
        }
    }

    /**
     * Publishes a Bulk of messages to a given queue.
     *
     * @param Collection $messages
     * @param string $queue
     * @return void
     * @throws AMQPConnectionClosedException
     */
    public function publishBulkMessagesToQueue(Collection $messages, string $queue)
    {
        try {
            if (!self::$connection || !self::$connection->isConnected()) {
                throw new AMQPConnectionClosedException(
                    'Failed to establish connection to RabbitMQ server.'
                );
            }

            self::$channel->confirm_select();

            $messages->map(function (AMQPMessage $msg) use($queue){
                self::$channel->batch_basic_publish($msg, '', $queue);
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

    /**
     * Publishes a Bulk of messages to a given exchange.
     *
     * @param Collection $messages
     * @param string $exchange
     * @return void
     * @throws AMQPConnectionClosedException
     */
    public function publishBulkMessagesToExchange(Collection $messages, string $exchange)
    {
        try {
            if (!self::$connection || !self::$connection->isConnected()) {
                throw new AMQPConnectionClosedException(
                    'Failed to establish connection to RabbitMQ server.'
                );
            }

            self::$channel->confirm_select();

            $messages->map(function (AMQPMessage $msg) use($exchange){
                self::$channel->batch_basic_publish($msg, $exchange,'');
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

    /**
     * Publishes a message to a given exchange.
     *
     * @param AMQPMessage $message
     * @param string $exchange
     * @param string $routingKey
     * @return void
     * @throws AMQPConnectionClosedException
     */
    public function publishMessageToExchange(AMQPMessage $message, string $exchange, string $routingKey = '')
    {
        try {
            if (!self::$connection || !self::$connection->isConnected()) {
                throw new AMQPConnectionClosedException(
                    'Failed to establish connection to RabbitMQ server.'
                );
            }

            self::$channel->basic_publish($message, $exchange, $routingKey);

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
                [$message->body]
            );
        }
    }

    /**
     * Acknowledges a message.
     *
     * @param AMQPMessage $message
     * @return void
     */
    public function acknowledgeMessage(AMQPMessage $message)
    {
        self::$channel->basic_ack($message->delivery_info['delivery_tag']);
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
     * Closes the connection to the RabbitMQ server.
     *
     * @return void
     */
    public function closeConnection()
    {
        isset(self::$connection) ? self::$connection->close() : self::$connection = null;
        isset(self::$channel) ? self::$channel->close() : self::$channel = null;
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

}
