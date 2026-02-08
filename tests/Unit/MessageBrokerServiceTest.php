<?php

namespace Aachich\MessageBroker\Tests\Unit;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Aachich\MessageBroker\Services\MessageBrokerService;
use Aachich\MessageBroker\Contracts\AMQPMessageServiceInterface;
use Mockery;

class MessageBrokerServiceTest extends TestCase
{
    private $amqpMessageServiceMock;
    private $messageBrokerService;

    protected function setUp(): void
    {
        $this->amqpMessageServiceMock = $this->createMock(AMQPMessageServiceInterface::class);
        $this->messageBrokerService = new MessageBrokerService($this->amqpMessageServiceMock);
    }

    public function testConnect_ShouldSucceed(): void
    {
        $this->amqpMessageServiceMock->expects($this->once())
            ->method('connect');

        $this->messageBrokerService->connect();
    }

    public function testConsumeMessage_ShouldSucceed(): void
    {
        $consumeQueue = 'testQueue';
        $callback = function ($msg) {
            return true;
        };

        $this->amqpMessageServiceMock->expects($this->once())
            ->method('consumeMessageFromQueue')
            ->with(
                $this->equalTo($consumeQueue),
                $this->callback(function ($param) {
                    return is_callable($param);
                })
            );

        $this->messageBrokerService->consumeMessage($consumeQueue, $callback);
    }

    public function testPublishToQueue_ShouldSucceed(): void
    {
        $messageContent = 'testMessage';
        $queue = 'testQueue';
        $headers = ['key' => 'value'];

        $this->amqpMessageServiceMock->expects($this->once())
            ->method('publishMessageToQueue')
            ->with(
                $this->equalTo($messageContent),
                $this->equalTo($queue),
                $this->equalTo($headers)
            );

        $this->messageBrokerService->publishToQueue($messageContent, $queue, $headers);
    }

    public function testPublishToQueue_WithoutHeaders_ShouldSucceed(): void
    {
        $messageContent = ['data' => 'test'];
        $queue = 'testQueue';

        $this->amqpMessageServiceMock->expects($this->once())
            ->method('publishMessageToQueue')
            ->with(
                $this->equalTo($messageContent),
                $this->equalTo($queue),
                $this->equalTo([])
            );

        $this->messageBrokerService->publishToQueue($messageContent, $queue);
    }

    public function testPublishToExchange_ShouldSucceed(): void
    {
        $messageContent = 'testMessage';
        $exchangeName = 'testExchange';
        $headers = ['key' => 'value'];
        $routingKey = 'test.routing.key';

        $this->amqpMessageServiceMock->expects($this->once())
            ->method('publishMessageToExchange')
            ->with(
                $this->equalTo($messageContent),
                $this->equalTo($exchangeName),
                $this->equalTo($headers),
                $this->equalTo($routingKey)
            );

        $this->messageBrokerService->publishToExchange($messageContent, $exchangeName, $headers, $routingKey);
    }

    public function testPublishBulkMessagesToQueue_ShouldSucceed(): void
    {
        $messages = new Collection(['msg1', 'msg2', 'msg3']);
        $queue = 'testQueue';
        $headers = ['batch' => true];

        $this->amqpMessageServiceMock->expects($this->once())
            ->method('publishBulkMessagesToQueue')
            ->with(
                $this->equalTo($messages),
                $this->equalTo($queue),
                $this->equalTo($headers)
            );

        $this->messageBrokerService->publishBulkMessagesToQueue($messages, $queue, $headers);
    }

    public function testPublishBulkMessagesToExchange_ShouldSucceed(): void
    {
        $messages = new Collection(['msg1', 'msg2']);
        $exchange = 'testExchange';
        $headers = [];

        $this->amqpMessageServiceMock->expects($this->once())
            ->method('publishBulkMessagesToExchange')
            ->with(
                $this->equalTo($messages),
                $this->equalTo($exchange),
                $this->equalTo($headers)
            );

        $this->messageBrokerService->publishBulkMessagesToExchange($messages, $exchange, $headers);
    }

    public function testGetStatus_ShouldReturnStatus(): void
    {
        $expectedStatus = [
            'brokerName' => 'RabbitMQ',
            'connect' => true,
            'consuming' => false
        ];

        $this->amqpMessageServiceMock->expects($this->once())
            ->method('getStatus')
            ->willReturn($expectedStatus);

        $result = $this->messageBrokerService->getStatus();

        $this->assertEquals($expectedStatus, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
