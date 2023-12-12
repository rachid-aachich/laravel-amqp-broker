<?php

namespace MaroEco\MessageBroker\Services;

use MaroEco\MessageBroker\Contracts\AMQPHelperServiceInterface;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;

class AMQPHelperService implements AMQPHelperServiceInterface
{

    /**
     * Increments the delivery attempts for a given message.
     *
     * @param AMQPMessage $message
     * @return array
     */
    public function incrementDeliveryAttempts($message)
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
    public function getNewMessageIncrementHeaders($message)
    {
        $headers = $this->incrementDeliveryAttempts($message);

        return $this->createPersistenceAMQPMessage($message->getBody(), $headers);
    }

    /**
     * Converts an array of headers into an AMQPTable.
     *
     * @param array|null $headers
     * @return AMQPTable
     */
    public function createAMQPTableFromArray($headers = null)
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
    public function getHeadersFromAMQPMessage(AMQPMessage $message) : array
    {
        return $message->get('application_headers')->getNativeData();
    }

    public function getDeliveryAttempts(AMQPMessage $message) : int
    {
        return $this->getHeadersFromAMQPMessage($message)['x-delivery-attempts'] ?? 0;
    }

    /**
     * Checks if the delivery limit has been exceeded for a given message.
     *
     * @param AMQPMessage $message
     * @return bool
     */
    public function hasExceededDeliveryLimit(AMQPMessage $message)
    {
        $headers = $this->getHeadersFromAMQPMessage($message);
        $deliveryAttempts = $headers['x-delivery-attempts'] ?? 0;
        $redelivered = $message->get('redelivered');

        if ($redelivered && array_key_exists('x-delivery-attempts', $headers)) {
            unset($headers['x-delivery-attempts']);
        }

        return $deliveryAttempts > config('messagebroker.rabbitmq.maxRMQDeliveryLimit');
    }

    public function isMessageRejectable($message): bool
    {
        return empty($message) || !$message || $this->hasExceededDeliveryLimit($message);
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
     * Creates an instance of AMQPMessage with persistent delivery mode based on the provided content and headers.
     *
     * @param mixed $content The content of the message. If an instance of AMQPMessage is provided,
     *                       the content will be extracted from it; otherwise, it will be JSON-encoded.
     * @param array $headers An associative array containing headers to be set for the message.
     *                       If $content is an instance of AMQPMessage and $headers is empty,
     *                       the headers will be extracted from the original message.
     *
     * @return AMQPMessage The created instance of AMQPMessage with persistent delivery mode.
     *
     */
    public function createPersistenceAMQPMessage($content, array $headers = []) : AMQPMessage
    {
        $msgIsAMQPMessage = $this->isAMQPMessage($content);

        $message = $msgIsAMQPMessage ? $content->getBody() : $content;
        $headers = $msgIsAMQPMessage && count($headers) == 0 ? $this->getHeadersFromAMQPMessage($content) : $headers;

        $amqpMessage = new AMQPMessage($message, [
            'application_headers' => $this->createAMQPTableFromArray($headers),
            'delivery_mode' => 2
        ]);

        return $amqpMessage;
    }
}
