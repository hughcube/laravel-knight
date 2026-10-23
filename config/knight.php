<?php

return [
    'opcache' => [
        'route_prefix' => false,
    ],

    'request' => [
        'route_prefix' => false,
        'signature'    => [
            'disabled' => env('KNIGHT_REQUEST_SIGNATURE_DISABLED', false),
        ],
    ],

    'ping' => [
        'route_prefix' => null,
    ],

    'phpinfo' => [
        'route_prefix' => false,
    ],

    'devops' => [
        'route_prefix' => null,
    ],

    'schedule' => [
        'heavy_commands' => [],
    ],
];
