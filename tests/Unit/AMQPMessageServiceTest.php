<?php

namespace Aachich\MessageBroker\Tests\Unit;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Aachich\MessageBroker\Services\AMQPMessageService;
use Aachich\MessageBroker\Contracts\AMQPHelperServiceInterface;
use Aachich\MessageBroker\Contracts\BrokerRepoInterface;
use PhpAmqpLib\Message\AMQPMessage;
use Mockery;

class AMQPMessageServiceTest extends TestCase
{
    private $helperServiceMock;
    private $brokerRepoMock;
    private AMQPMessageService $amqpMessageService;

    protected function setUp(): void
    {
        $this->helperServiceMock = Mockery::mock(AMQPHelperServiceInterface::class);
        $this->brokerRepoMock = Mockery::mock(BrokerRepoInterface::class);
        
        $this->amqpMessageService = new AMQPMessageService(
            $this->helperServiceMock,
            $this->brokerRepoMock
        );
    }

    public function testConnect_ShouldDelegateToRepository(): void
    {
        $this->brokerRepoMock->shouldReceive('connect')
            ->once();

        $this->amqpMessageService->connect();
        
        $this->assertTrue(true); // Mockery verifies the expectation
    }

    public function testValidateMessage_WithValidMessage_ShouldReturnTrue(): void
    {
        $message = Mockery::mock(AMQPMessage::class);

        $this->helperServiceMock->shouldReceive('isMessageRejectable')
            ->with($message)
            ->once()
            ->andReturn(false);

        $result = $this->amqpMessageService->validateMessage($message);

        $this->assertTrue($result);
    }

    public function testValidateMessage_WithRejectableMessage_ShouldReturnFalse(): void
    {
        $message = Mockery::mock(AMQPMessage::class);

        $this->helperServiceMock->shouldReceive('isMessageRejectable')
            ->with($message)
            ->once()
            ->andReturn(true);

        $this->brokerRepoMock->shouldReceive('rejectMessage')
            ->with($message)
            ->once();

        $result = $this->amqpMessageService->validateMessage($message);

        $this->assertFalse($result);
    }

    public function testTakeMessage_ShouldAcknowledgeMessage(): void
    {
        $message = Mockery::mock(AMQPMessage::class);

        $this->brokerRepoMock->shouldReceive('acknowledgeMessage')
            ->with($message)
            ->once();

        $this->amqpMessageService->takeMessage($message);
        
        $this->assertTrue(true); // Mockery verifies the expectation
    }

    public function testRejectMessage_ShouldRejectMessage(): void
    {
        $message = Mockery::mock(AMQPMessage::class);

        $this->brokerRepoMock->shouldReceive('rejectMessage')
            ->with($message)
            ->once();

        $this->amqpMessageService->rejectMessage($message);
        
        $this->assertTrue(true); // Mockery verifies the expectation
    }

    public function testConsumeMessageFromQueue_ShouldDelegateToRepository(): void
    {
        $queue = 'test-queue';
        $callback = function ($msg) { return true; };

        $this->brokerRepoMock->shouldReceive('consumeMessageFromQueue')
            ->with($queue, $callback)
            ->once();

        $this->amqpMessageService->consumeMessageFromQueue($queue, $callback);
        
        $this->assertTrue(true); // Mockery verifies the expectation
    }

    public function testPublishMessageToQueue_ShouldCreateAndPublishMessage(): void
    {
        $content = ['data' => 'test'];
        $queue = 'test-queue';
        $headers = ['key' => 'value'];
        $amqpMessage = Mockery::mock(AMQPMessage::class);

        $this->helperServiceMock->shouldReceive('createPersistenceAMQPMessage')
            ->with($content, $headers)
            ->once()
            ->andReturn($amqpMessage);

        $this->brokerRepoMock->shouldReceive('publishMessageToQueue')
            ->with($amqpMessage, $queue)
            ->once();

        $this->amqpMessageService->publishMessageToQueue($content, $queue, $headers);
        
        $this->assertTrue(true); // Mockery verifies the expectation
    }

    public function testPublishBulkMessagesToQueue_ShouldCreateAndPublishMessages(): void
    {
        $messages = new Collection(['msg1', 'msg2']);
        $queue = 'test-queue';
        $headers = [];

        $amqpMessage1 = Mockery::mock(AMQPMessage::class);
        $amqpMessage2 = Mockery::mock(AMQPMessage::class);

        $this->helperServiceMock->shouldReceive('createPersistenceAMQPMessage')
            ->with('msg1', $headers)
            ->once()
            ->andReturn($amqpMessage1);

        $this->helperServiceMock->shouldReceive('createPersistenceAMQPMessage')
            ->with('msg2', $headers)
            ->once()
            ->andReturn($amqpMessage2);

        $this->brokerRepoMock->shouldReceive('publishBulkMessagesToQueue')
            ->once()
            ->with(Mockery::on(function ($arg) use ($amqpMessage1, $amqpMessage2) {
                return $arg instanceof Collection && $arg->count() === 2;
            }), $queue);

        $this->amqpMessageService->publishBulkMessagesToQueue($messages, $queue, $headers);
        
        $this->assertTrue(true); // Mockery verifies the expectation
    }

    public function testPublishMessageToExchange_ShouldCreateAndPublishMessage(): void
    {
        $content = 'test message';
        $exchange = 'test-exchange';
        $headers = ['header' => 'value'];
        $routingKey = 'routing.key';
        $amqpMessage = Mockery::mock(AMQPMessage::class);

        $this->helperServiceMock->shouldReceive('createPersistenceAMQPMessage')
            ->with($content, $headers, $routingKey)
            ->once()
            ->andReturn($amqpMessage);

        $this->brokerRepoMock->shouldReceive('publishMessageToExchange')
            ->with($amqpMessage, $exchange, $routingKey)
            ->once();

        $this->amqpMessageService->publishMessageToExchange($content, $exchange, $headers, $routingKey);
        
        $this->assertTrue(true); // Mockery verifies the expectation
    }

    public function testPublishBulkMessagesToExchange_ShouldCreateAndPublishMessages(): void
    {
        $messages = new Collection(['msg1', 'msg2']);
        $exchange = 'test-exchange';
        $headers = [];

        $amqpMessage1 = Mockery::mock(AMQPMessage::class);
        $amqpMessage2 = Mockery::mock(AMQPMessage::class);

        $this->helperServiceMock->shouldReceive('createPersistenceAMQPMessage')
            ->with('msg1', $headers)
            ->once()
            ->andReturn($amqpMessage1);

        $this->helperServiceMock->shouldReceive('createPersistenceAMQPMessage')
            ->with('msg2', $headers)
            ->once()
            ->andReturn($amqpMessage2);

        $this->brokerRepoMock->shouldReceive('publishBulkMessagesToExchange')
            ->once()
            ->with(Mockery::on(function ($arg) {
                return $arg instanceof Collection && $arg->count() === 2;
            }), $exchange);

        $this->amqpMessageService->publishBulkMessagesToExchange($messages, $exchange, $headers);
        
        $this->assertTrue(true); // Mockery verifies the expectation
    }

    public function testGetStatus_ShouldReturnRepositoryStatus(): void
    {
        $expectedStatus = [
            'brokerName' => 'RabbitMQ',
            'connect' => true,
            'consuming' => false
        ];

        $this->brokerRepoMock->shouldReceive('status')
            ->once()
            ->andReturn($expectedStatus);

        $result = $this->amqpMessageService->getStatus();

        $this->assertEquals($expectedStatus, $result);
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
