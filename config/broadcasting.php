<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Broadcaster
    |--------------------------------------------------------------------------
    |
    | This option controls the default broadcaster that will be used by the
    | framework when an event needs to be broadcast. You may set this to
    | any of the connections defined in the "connections" array below.
    |
    | Supported: "pusher", "ably", "redis", "log", "null"
    |
    */

    'default' => 'socketio',

    /*
    |--------------------------------------------------------------------------
    | Broadcast Connections
    |--------------------------------------------------------------------------
    |
    | Here you may define all of the broadcast connections that will be used
    | to broadcast events to other systems or over websockets. Samples of
    | each available type of connection are provided inside this array.
    |
    */

    'connections' => [

        'socketio' => [
            'driver' => 'socket.io',
            'host' => env('SOCKET_HOST', '127.0.0.1'),
            'port' => (int) env('SOCKET_PORT', 3001),
            'scheme' => env('SOCKET_SCHEME', 'http'),
            'options' => [
                'secure' => false,
                'reconnect' => true,
                'transports' => ['websocket'],
            ],
        ],

        'socketio' => [
            'driver' => 'socket.io',
            'host' => env('SOCKETIO_HOST', 'localhost'),
            'port' => env('SOCKETIO_PORT', 3001),
            'namespace' => 'App\Events',
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ],

        'ably' => [
            'driver' => 'ably',
            'key' => env('ABLY_KEY'),
        ],

        'redis' => [
            'driver' => 'redis',
            'connection' => 'default',
        ],

        'log' => [
            'driver' => 'log',
        ],

        'null' => [
            'driver' => 'null',
        ],

    ],

];
