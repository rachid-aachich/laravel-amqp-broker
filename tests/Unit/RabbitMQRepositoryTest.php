<?php

namespace Aachich\MessageBroker\Tests\Unit;

use Illuminate\Support\Collection;
use PHPUnit\Framework\TestCase;
use Aachich\MessageBroker\Repositories\RabbitMQRepository;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Channel\AMQPChannel;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use Mockery;
use ReflectionClass;

/**
 * Tests for RabbitMQRepository.
 * 
 * Note: These tests use reflection to set static properties since the repository
 * uses static connection/channel. The constructor requires Laravel's config() helper,
 * so we create a partial mock that skips the constructor.
 */
class RabbitMQRepositoryTest extends TestCase
{
    private $repository;

    protected function setUp(): void
    {
        $this->resetStaticProperties();
        
        // Create mock that doesn't call constructor (avoids config() dependency)
        $this->repository = Mockery::mock(RabbitMQRepository::class)
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
        
        // Set default values that would normally come from config
        $reflection = new ReflectionClass(RabbitMQRepository::class);
        
        $maxRetries = $reflection->getProperty('maxRetries');
        $maxRetries->setAccessible(true);
        $maxRetries->setValue($this->repository, 3);

        $retryDelay = $reflection->getProperty('retryDelay');
        $retryDelay->setAccessible(true);
        $retryDelay->setValue($this->repository, 3000);

        $maxDeliveryLimit = $reflection->getProperty('maxDeliveryLimit');
        $maxDeliveryLimit->setAccessible(true);
        $maxDeliveryLimit->setValue($this->repository, 30);
    }

    private function resetStaticProperties(): void
    {
        $reflection = new ReflectionClass(RabbitMQRepository::class);
        
        $connectionProperty = $reflection->getProperty('connection');
        $connectionProperty->setAccessible(true);
        $connectionProperty->setValue(null, null);

        $channelProperty = $reflection->getProperty('channel');
        $channelProperty->setAccessible(true);
        $channelProperty->setValue(null, null);
    }

    private function setStaticConnection($connection): void
    {
        $reflection = new ReflectionClass(RabbitMQRepository::class);
        $property = $reflection->getProperty('connection');
        $property->setAccessible(true);
        $property->setValue(null, $connection);
    }

    private function setStaticChannel($channel): void
    {
        $reflection = new ReflectionClass(RabbitMQRepository::class);
        $property = $reflection->getProperty('channel');
        $property->setAccessible(true);
        $property->setValue(null, $channel);
    }

    public function testStatus_WhenNotConnected_ShouldReturnDisconnectedStatus(): void
    {
        $result = $this->repository->status();

        $this->assertEquals('RabbitMQ', $result['brokerName']);
        $this->assertFalse($result['connect']);
        $this->assertFalse($result['consuming']);
    }

    public function testStatus_WhenConnected_ShouldReturnConnectedStatus(): void
    {
        $connectionMock = Mockery::mock(AMQPStreamConnection::class);
        $connectionMock->shouldReceive('isConnected')->andReturn(true);

        $channelMock = Mockery::mock(AMQPChannel::class);
        $channelMock->shouldReceive('is_consuming')->andReturn(true);

        $this->setStaticConnection($connectionMock);
        $this->setStaticChannel($channelMock);

        $result = $this->repository->status();

        $this->assertEquals('RabbitMQ', $result['brokerName']);
        $this->assertTrue($result['connect']);
        $this->assertTrue($result['consuming']);
    }

    public function testAcknowledgeMessage_ShouldCallBasicAck(): void
    {
        $deliveryTag = 123;
        
        $messageMock = Mockery::mock(AMQPMessage::class);
        $messageMock->delivery_info = ['delivery_tag' => $deliveryTag];

        $channelMock = Mockery::mock(AMQPChannel::class);
        $channelMock->shouldReceive('basic_ack')
            ->with($deliveryTag)
            ->once();

        $this->setStaticChannel($channelMock);

        $this->repository->acknowledgeMessage($messageMock);
        
        $this->assertTrue(true); // Verify no exception
    }

    public function testRejectMessage_ShouldCallNack(): void
    {
        $messageMock = Mockery::mock(AMQPMessage::class);
        $messageMock->shouldReceive('nack')->once();

        $this->repository->rejectMessage($messageMock);
        
        $this->assertTrue(true); // Verify no exception
    }

    public function testPublishMessageToQueue_WhenConnected_ShouldPublish(): void
    {
        $message = new AMQPMessage('test message');
        $queue = 'test-queue';

        $connectionMock = Mockery::mock(AMQPStreamConnection::class);
        $connectionMock->shouldReceive('isConnected')->andReturn(true);

        $channelMock = Mockery::mock(AMQPChannel::class);
        $channelMock->shouldReceive('basic_publish')
            ->with($message, '', $queue)
            ->once();

        $this->setStaticConnection($connectionMock);
        $this->setStaticChannel($channelMock);

        $this->repository->publishMessageToQueue($message, $queue);
        
        $this->assertTrue(true); // Verify no exception
    }

    public function testPublishMessageToExchange_WhenConnected_ShouldPublish(): void
    {
        $message = new AMQPMessage('test message');
        $exchange = 'test-exchange';
        $routingKey = 'test.routing.key';

        $connectionMock = Mockery::mock(AMQPStreamConnection::class);
        $connectionMock->shouldReceive('isConnected')->andReturn(true);

        $channelMock = Mockery::mock(AMQPChannel::class);
        $channelMock->shouldReceive('basic_publish')
            ->with($message, $exchange, $routingKey)
            ->once();

        $this->setStaticConnection($connectionMock);
        $this->setStaticChannel($channelMock);

        $this->repository->publishMessageToExchange($message, $exchange, $routingKey);
        
        $this->assertTrue(true); // Verify no exception
    }

    public function testPublishBulkMessagesToQueue_WhenConnected_ShouldBatchPublish(): void
    {
        $msg1 = new AMQPMessage('msg1');
        $msg2 = new AMQPMessage('msg2');
        $messages = new Collection([$msg1, $msg2]);
        $queue = 'test-queue';

        $connectionMock = Mockery::mock(AMQPStreamConnection::class);
        $connectionMock->shouldReceive('isConnected')->andReturn(true);

        $channelMock = Mockery::mock(AMQPChannel::class);
        $channelMock->shouldReceive('confirm_select')->once();
        $channelMock->shouldReceive('batch_basic_publish')->twice();
        $channelMock->shouldReceive('publish_batch')->once();
        $channelMock->shouldReceive('wait_for_pending_acks_returns')->once();

        $this->setStaticConnection($connectionMock);
        $this->setStaticChannel($channelMock);

        $this->repository->publishBulkMessagesToQueue($messages, $queue);
        
        $this->assertTrue(true); // Verify no exception
    }

    public function testPublishBulkMessagesToExchange_WhenConnected_ShouldBatchPublish(): void
    {
        $msg1 = new AMQPMessage('msg1');
        $msg2 = new AMQPMessage('msg2');
        $messages = new Collection([$msg1, $msg2]);
        $exchange = 'test-exchange';

        $connectionMock = Mockery::mock(AMQPStreamConnection::class);
        $connectionMock->shouldReceive('isConnected')->andReturn(true);

        $channelMock = Mockery::mock(AMQPChannel::class);
        $channelMock->shouldReceive('confirm_select')->once();
        $channelMock->shouldReceive('batch_basic_publish')->twice();
        $channelMock->shouldReceive('publish_batch')->once();
        $channelMock->shouldReceive('wait_for_pending_acks_returns')->once();

        $this->setStaticConnection($connectionMock);
        $this->setStaticChannel($channelMock);

        $this->repository->publishBulkMessagesToExchange($messages, $exchange);
        
        $this->assertTrue(true); // Verify no exception
    }

    public function testCloseConnection_ShouldCloseChannelAndConnection(): void
    {
        $connectionMock = Mockery::mock(AMQPStreamConnection::class);
        $connectionMock->shouldReceive('close')->once();

        $channelMock = Mockery::mock(AMQPChannel::class);
        $channelMock->shouldReceive('close')->once();

        $this->setStaticConnection($connectionMock);
        $this->setStaticChannel($channelMock);

        $this->repository->closeConnection();
        
        $this->assertTrue(true); // Verify no exception
    }

    public function testCloseConnection_WhenNotConnected_ShouldNotThrow(): void
    {
        // No connection set, should not throw
        $this->repository->closeConnection();
        $this->assertTrue(true);
    }

    protected function tearDown(): void
    {
        $this->resetStaticProperties();
        Mockery::close();
    }
}
