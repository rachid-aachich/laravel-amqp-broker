<?php

namespace MaroEco\MessageBroker;

use Illuminate\Support\ServiceProvider;
use MaroEco\MessageBroker\Contracts\BrokerRepoInterface;
use MaroEco\MessageBroker\Services\MessageBrokerService;
use MaroEco\MessageBroker\Repositories\RabbitMQRepository;

class MessageBrokerServiceProvider extends ServiceProvider
{
    protected const CONFIG_PATH = '/config/messagebroker.php';

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . self::CONFIG_PATH, 'messagebroker'
        );

        $this->app->singleton(BrokerRepoInterface::class, function ($app) {
            return new RabbitMQRepository;
        });

        $this->app->singleton(MessageBrokerInterface::class, function ($app) {
            $brokerRepo = $app->make(BrokerRepoInterface::class);

            return new MessageBrokerService($brokerRepo);
        });
    }

    public function boot()
    {
        // Publish configuration file
        $this->publishes([
            __DIR__ . self::CONFIG_PATH => $this->app->basePath() . self::CONFIG_PATH,
        ], 'config');


    }
}
