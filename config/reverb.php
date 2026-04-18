<?php

return [

    /*
    |--------------------------------------------------------------------------
    | WebSocket Server Host
    |--------------------------------------------------------------------------
    |
    | This value determines the host address that the WebSocket server will
    | bind to. By default, it will bind to all available IPv4 interfaces on
    | the host machine allowing clients to connect from any available host.
    |
    */

    'host' => env('REVERB_HOST', '127.0.0.1'),

    /*
    |--------------------------------------------------------------------------
    | WebSocket Server Port
    |--------------------------------------------------------------------------
    |
    | This value determines the port that the WebSocket server will listen on.
    | By default it will listen on port 8080 which is a common port many
    | proxies can be configured to route WebSocket traffic towards.
    |
    */

    'port' => env('REVERB_PORT', 8080),

    /*
    |--------------------------------------------------------------------------
    | WebSocket Server Options
    |--------------------------------------------------------------------------
    |
    | This array contains all of the underlying WebSocket server options that
    | are provided by the Reverb server using the Swoole HTTP server.
    |
    */

    'options' => [
        'app_id' => env('REVERB_APP_ID'),
        'app_key' => env('REVERB_APP_KEY'),
        'app_secret' => env('REVERB_APP_SECRET'),
        'host' => env('REVERB_HOST', '127.0.0.1'),
        'port' => env('REVERB_PORT', 8080),
        'max_request_size' => env('REVERB_MAX_REQUEST_SIZE', 10_000),
    ],

];

