<?php

namespace Aachich\MessageBroker\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Aachich\MessageBroker\Services\AMQPHelperService;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Mockery;

class AMQPHelperServiceTest extends TestCase
{
    private AMQPHelperService $helperService;

    protected function setUp(): void
    {
        $this->helperService = new AMQPHelperService();
    }

    public function testCreateAMQPTableFromArray_WithValidArray_ShouldReturnAMQPTable(): void
    {
        $headers = ['key1' => 'value1', 'key2' => 'value2'];

        $result = $this->helperService->createAMQPTableFromArray($headers);

        $this->assertInstanceOf(AMQPTable::class, $result);
        $this->assertEquals($headers, $result->getNativeData());
    }

    public function testCreateAMQPTableFromArray_WithNull_ShouldReturnEmptyAMQPTable(): void
    {
        $result = $this->helperService->createAMQPTableFromArray(null);

        $this->assertInstanceOf(AMQPTable::class, $result);
        $this->assertEquals([], $result->getNativeData());
    }

    public function testCreateAMQPTableFromArray_WithEmptyArray_ShouldReturnEmptyAMQPTable(): void
    {
        $result = $this->helperService->createAMQPTableFromArray([]);

        $this->assertInstanceOf(AMQPTable::class, $result);
        $this->assertEquals([], $result->getNativeData());
    }

    public function testGetHeadersFromAMQPMessage_ShouldReturnHeaders(): void
    {
        $headers = ['x-custom-header' => 'custom-value'];
        $amqpTable = new AMQPTable($headers);
        
        $message = new AMQPMessage('test body', [
            'application_headers' => $amqpTable
        ]);

        $result = $this->helperService->getHeadersFromAMQPMessage($message);

        $this->assertEquals($headers, $result);
    }

    public function testIsMessageRejectable_WithEmptyMessage_ShouldReturnTrue(): void
    {
        $result = $this->helperService->isMessageRejectable('');

        $this->assertTrue($result);
    }

    public function testIsMessageRejectable_WithNullMessage_ShouldReturnTrue(): void
    {
        $result = $this->helperService->isMessageRejectable(null);

        $this->assertTrue($result);
    }

    public function testIsMessageRejectable_WithFalseMessage_ShouldReturnTrue(): void
    {
        $result = $this->helperService->isMessageRejectable(false);

        $this->assertTrue($result);
    }

    public function testIsMessageRejectable_WithValidMessage_ShouldReturnFalse(): void
    {
        $message = new AMQPMessage('valid message body');

        $result = $this->helperService->isMessageRejectable($message);

        $this->assertFalse($result);
    }

    public function testIsAMQPMessage_WithAMQPMessage_ShouldReturnTrue(): void
    {
        $message = new AMQPMessage('test');

        $result = $this->helperService->isAMQPMessage($message);

        $this->assertTrue($result);
    }

    public function testIsAMQPMessage_WithString_ShouldReturnFalse(): void
    {
        $result = $this->helperService->isAMQPMessage('not an AMQP message');

        $this->assertFalse($result);
    }

    public function testIsAMQPMessage_WithArray_ShouldReturnFalse(): void
    {
        $result = $this->helperService->isAMQPMessage(['data' => 'value']);

        $this->assertFalse($result);
    }

    public function testCreatePersistenceAMQPMessage_WithStringContent_ShouldReturnPersistentMessage(): void
    {
        $content = 'test message content';
        $headers = ['key' => 'value'];

        $result = $this->helperService->createPersistenceAMQPMessage($content, $headers);

        $this->assertInstanceOf(AMQPMessage::class, $result);
        $this->assertEquals($content, $result->getBody());
        $this->assertEquals(2, $result->get('delivery_mode'));
        $this->assertEquals($headers, $result->get('application_headers')->getNativeData());
    }

    public function testCreatePersistenceAMQPMessage_WithEmptyHeaders_ShouldReturnMessageWithEmptyHeaders(): void
    {
        $content = 'test message';

        $result = $this->helperService->createPersistenceAMQPMessage($content);

        $this->assertInstanceOf(AMQPMessage::class, $result);
        $this->assertEquals($content, $result->getBody());
        $this->assertEquals(2, $result->get('delivery_mode'));
        $this->assertEquals([], $result->get('application_headers')->getNativeData());
    }

    public function testCreatePersistenceAMQPMessage_WithAMQPMessageContent_ShouldExtractBodyAndHeaders(): void
    {
        $originalHeaders = ['original-header' => 'original-value'];
        $originalBody = 'original message body';
        
        $originalMessage = new AMQPMessage($originalBody, [
            'application_headers' => new AMQPTable($originalHeaders)
        ]);

        $result = $this->helperService->createPersistenceAMQPMessage($originalMessage);

        $this->assertInstanceOf(AMQPMessage::class, $result);
        $this->assertEquals($originalBody, $result->getBody());
        $this->assertEquals(2, $result->get('delivery_mode'));
        $this->assertEquals($originalHeaders, $result->get('application_headers')->getNativeData());
    }

    public function testCreatePersistenceAMQPMessage_WithAMQPMessageAndCustomHeaders_ShouldUseCustomHeaders(): void
    {
        $originalHeaders = ['original-header' => 'original-value'];
        $customHeaders = ['custom-header' => 'custom-value'];
        $originalBody = 'original message body';
        
        $originalMessage = new AMQPMessage($originalBody, [
            'application_headers' => new AMQPTable($originalHeaders)
        ]);

        $result = $this->helperService->createPersistenceAMQPMessage($originalMessage, $customHeaders);

        $this->assertInstanceOf(AMQPMessage::class, $result);
        $this->assertEquals($originalBody, $result->getBody());
        $this->assertEquals($customHeaders, $result->get('application_headers')->getNativeData());
    }

    protected function tearDown(): void
    {
        Mockery::close();
    }
}
