<?php


return [

    'schemaVersion'                 => 1,
    'defaultDevice'                 => "Xilog",
    'default'                       => "rabbitmq",
    'rabbitmq'                      => [
        'maxRMQDeliveryLimit'           => 30,
        'maxRMQConnectionRetries'       => 3,
        'maxRMQConnectionRetryDelay'    => 3000,
        'queue_consumer'                => env('RABBITMQ_QUEUE_CONSUMER', 'ingnotification'),
        'reject_queue'                  => env('RABBITMQ_QUEUE_REJECT', 'notReach'),
        'publish_queue'                 => env('RABBITMQ_QUEUE_PUBLISHER', 'grouping'),
        'hostname'                      => env('RABBITMQ_HOST'),
        'username'                      => env('RABBITMQ_USER'),
        'port'                          => env('RABBITMQ_PORT'),
        'password'                      => env('RABBITMQ_PASSWORD'),
        'vhost'                         => env('RABBITMQ_VHOST')
    ],
    'defaultQueues'                 => [
        env('RABBITMQ_QUEUE_REJECT', 'extractionfailure'),
        env('RABBITMQ_QUEUE_CONSUMER', 'ingnotification'),
        env('RABBITMQ_QUEUE_REJECT', 'extractionfailure')
    ]

];
