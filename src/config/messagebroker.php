<?php


return [

    'schemaVersion'                 => 1,
    'defaultDevice'                 => "Xilog",
    'default'                       => "rabbitmq",
    'rabbitmq'                      => [
        'maxRMQDeliveryLimit'           => env("RABBITMQ_maxRMQDeliveryLimit", 30),
        'maxRMQConnectionRetries'       => 3,
        'maxRMQConnectionRetryDelay'    => 3000,
        'queue_consumer'                => env('RABBITMQ_QUEUE_CONSUMER'),
        'reject_queue'                  => env('RABBITMQ_QUEUE_REJECT'),
        'publish_queue'                 => env('RABBITMQ_QUEUE_PUBLISHER'),
        'hostname'                      => env('RABBITMQ_HOST'),
        'username'                      => env('RABBITMQ_USER'),
        'port'                          => env('RABBITMQ_PORT'),
        'password'                      => env('RABBITMQ_PASSWORD'),
        'vhost'                         => env('RABBITMQ_VHOST')
    ],
    'defaultQueues'                 => [
        env('RABBITMQ_QUEUE_REJECT'),
        env('RABBITMQ_QUEUE_CONSUMER'),
        env('RABBITMQ_QUEUE_REJECT')
    ]

];
