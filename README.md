# MaroEco Message Broker Package

## Introduction

Welcome to the MaroEco Message Broker Package! This package serves as an abstraction for messaging brokers, making it easy to switch between different message brokers without needing to change your application code.

It is designed to be used in any Laravel application within MaroEco that relies on message brokers. Currently, it supports RabbitMQ but can be easily extended to support other message brokers.

# Installation

- Clone it from the repository here.
- Include it in your composer.json file.

# Usage

After the package has been installed, you should register your broker repository class in the ``AppServiceProvider``:
````
use MaroEco\MessageBroker\Facades\MessageBroker;

public function register()
{
    $this->app->bind('MessageBroker', function ($app) {
        // Replace YourMessageBrokerRepository with the class name of your message broker repository
        return new YourMessageBrokerRepository();
    });
}

````

Then, you can use the ``MessageBroker`` facade in your code:

````
use MaroEco\MessageBroker\Facades\MessageBroker;

MessageBroker::publishToQueue($message, $queue, $headers);
````

Optional: Add ``BROKER_NAME=rabbitmq`` in your .env file!

# Testing

To run the tests, you can use the following command:

````
vendor/bin/phpunit
````

# Contributing

For future support of any broker please submit your changes in a PR against 'main' and it will be reviewed.

# TODO:

- Complete comments in code (coming soon)
- Test the package thouroughly.
- Write edge-case tests. 


# License

This package as part of MaroEco is not open source, and it belongs to Terabyte-Software, please do not share without permission.