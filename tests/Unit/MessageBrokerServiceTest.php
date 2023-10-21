<?php

// tests/Unit/MessageBrokerServiceTest.php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use MaroEco\MessageBroker\Services\MessageBrokerService;
use MaroEco\MessageBroker\Contracts\BrokerRepoInterface;
use Mockery;

class MessageBrokerServiceTest extends TestCase
{
    public function testServiceMethodsCallRepositoryMethods()
    {
        $mock = Mockery::mock(BrokerRepoInterface::class);
        $mock->shouldReceive('connect')->once();
        $mock->shouldReceive('consumeMessageFromQueue')->with('queue', 'rejectQueue')->once();
        $mock->shouldReceive('publishMessageToQueue')->with('message', 'queue', 'headers')->once();
        $mock->shouldReceive('status')->once()->andReturn('status');

        $service = new MessageBrokerService($mock);

        $service->connect();
        $service->consumeMessageFromQueue('queue', 'rejectQueue');
        $service->publishToQueue('message', 'queue', 'headers');
        $this->assertEquals('status', $service->getStatus());
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
