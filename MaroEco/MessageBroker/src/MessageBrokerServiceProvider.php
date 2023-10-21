<?php

namespace MaroEco\MessageBroker;

use Illuminate\Support\ServiceProvider;
use MaroEco\MessageBroker\Contracts\BrokerRepoInterface;
use MaroEco\MessageBroker\Services\MessageBrokerService;
use MaroEco\MessageBroker\Repositories\RabbitMQRepository;
use MaroEco\MessageBroker\Exceptions\InvalidMessageBrokerNameException;

class MessageBrokerServiceProvider extends ServiceProvider
{
    protected const CONFIG_PATH = '/config/messagebroker.php';

    public function register()
    {
        $this->mergeConfigFrom(
            __DIR__ . self::CONFIG_PATH, 'messagebroker'
        );

        $this->app->singleton(BrokerRepoInterface::class, function ($app) {
            switch (env('BROKER_NAME', 'rabbitmq')) {
                case 'rabbitmq':
                    return new RabbitMQRepository;
                /*case 'testmq': // Any new broker will be added here in the future
                    return new TestMQRepository;*/
                default:
                    throw new InvalidMessageBrokerNameException("This Broker Name is Invalid");
            }
        });

        $this->app->singleton(MessageBrokerService::class, function ($app) {
            $config = $app->make('config')->get('messagebroker.default');
            $brokerRepo = $app->make('MaroEco\MessageBroker\Repositories\\'.ucfirst($config).'Repository');
    
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
