<?php

namespace MaroEco\MessageBroker;

use Illuminate\Support\ServiceProvider;

use MaroEco\MessageBroker\Contracts\AMQPHelperServiceInterface;
use MaroEco\MessageBroker\Contracts\AMQPMessageServiceInterface;
use MaroEco\MessageBroker\Contracts\BrokerRepoInterface;
use MaroEco\MessageBroker\Contracts\MessageBrokerInterface;

use MaroEco\MessageBroker\Services\MessageBrokerService;
use MaroEco\MessageBroker\Repositories\RabbitMQRepository;
use MaroEco\MessageBroker\Services\AMQPHelperService;
use MaroEco\MessageBroker\Services\AMQPMessageService;

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
