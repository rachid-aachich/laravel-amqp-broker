<?php

namespace Aachich\MessageBroker;

use Illuminate\Support\ServiceProvider;

use Aachich\MessageBroker\Contracts\AMQPHelperServiceInterface;
use Aachich\MessageBroker\Contracts\AMQPMessageServiceInterface;
use Aachich\MessageBroker\Contracts\BrokerRepoInterface;
use Aachich\MessageBroker\Contracts\MessageBrokerInterface;

use Aachich\MessageBroker\Services\MessageBrokerService;
use Aachich\MessageBroker\Repositories\RabbitMQRepository;
use Aachich\MessageBroker\Services\AMQPHelperService;
use Aachich\MessageBroker\Services\AMQPMessageService;

class MessageBrokerServiceProvider extends ServiceProvider
{
    protected const CONFIG_PATH = '/config/messagebroker.php';

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . self::CONFIG_PATH, 'messagebroker'
        );

        $this->app->singleton(BrokerRepoInterface::class, RabbitMQRepository::class);
        $this->app->singleton(MessageBrokerInterface::class, MessageBrokerService::class);
        $this->app->singleton(AMQPMessageServiceInterface::class, AMQPMessageService::class);
        $this->app->singleton(AMQPHelperServiceInterface::class, AMQPHelperService::class);
    }

    public function boot()
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . self::CONFIG_PATH => $this->app->basePath() . self::CONFIG_PATH,
        ], 'config');


    }
}
