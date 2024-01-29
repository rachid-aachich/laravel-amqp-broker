<?php

// tests/Unit/MessageBrokerServiceTest.php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use MaroEco\MessageBroker\Services\MessageBrokerService;
use MaroEco\MessageBroker\Contracts\BrokerRepoInterface;
use MaroEco\MessageBroker\Contracts\MessageBrokerInterface;
use MaroEco\MessageBroker\Contracts\AMQPMessageServiceInterface;
use MaroEco\MessageBroker\Services\AMQPMessageService;
use MaroEco\MessageBroker\Contracts\AMQPHelperServiceInterface;

use Mockery;

class MessageBrokerServiceTest extends TestCase
{

    private $amqpMessageServiceMock;
    private $messageBrokerService;

    protected function setUp(): void
    {
        // Mocking
        $this->amqpMessageServiceMock = $this->createMock(AMQPMessageServiceInterface::class);
        $this->messageBrokerService = new MessageBrokerService($this->amqpMessageServiceMock);
    }

    public function testConnect_ShouldSucceed()
    {
        $this->amqpMessageServiceMock->expects($this->once())
            ->method('connect');

        $this->messageBrokerService->connect();
    }

    public function testConsumeMessage_ShouldSucceed()
    {
        $consumeQueue = 'testQueue';
        $callback = function ($msg) {
            return true; // Simulate processing the message successfully
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
        // No need for assertions here because we assert using expects() method, we expect the method's behaviour
    }

    public function testPublishToQueue_ShouldSucceed()
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

    /**
     * Clean up the testing environment before the next test.
     *
     * @return void
     */
    public function tearDown(): void
    {
        // Ensure Mockery expectations have been met
        Mockery::close();
    }
}
