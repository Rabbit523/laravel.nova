<?php

use Monolog\Handler\StreamHandler;

if (!function_exists('get_rollbar_user')) {
    function get_rollbar_user()
    {
        if (Auth::check()) {
            $user = user();
            return array(
                'id' => $user->id, // required - value is a string
                'username' => $user->name, // required - value is a string
                'email' => $user->email // optional - value is a string
            );
        }
        return null;
    }
}

return [
    /*
    |--------------------------------------------------------------------------
    | Default Log Channel
    |--------------------------------------------------------------------------
    |
    | This option defines the default log channel that gets used when writing
    | messages to the logs. The name specified in this option should match
    | one of the channels defined in the "channels" configuration array.
    |
    */

    'default' => env('LOG_CHANNEL', 'stack'),

    /*
    |--------------------------------------------------------------------------
    | Log Channels
    |--------------------------------------------------------------------------
    |
    | Here you may configure the log channels for your application. Out of
    | the box, Laravel uses the Monolog PHP logging library. This gives
    | you a variety of powerful log handlers / formatters to utilize.
    |
    | Available Drivers: "single", "daily", "slack", "syslog",
    |                    "errorlog", "monolog",
    |                    "custom", "stack"
    |
    */

    'channels' => [
        'stack' => [
            'driver' => 'stack',
            'channels' => ['single', 'daily', 'slack', 'rollbar']
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'tap' => [App\Logging\CustomizeFormatter::class],
            'level' => 'debug'
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => 'debug',
            'days' => 7
        ],

        'slack' => [
            'driver' => 'slack',
            'tap' => [App\Logging\SlackLogger::class],
            'url' => env('LOG_SLACK_WEBHOOK_URL'),
            'username' => 'Laravel',
            'channel' => 'alerts',
            'emoji' => ':boom:',
            'level' => 'error'
        ],

        'stderr' => [
            'driver' => 'monolog',
            'handler' => StreamHandler::class,
            'with' => [
                'stream' => 'php://stderr'
            ]
        ],

        'syslog' => [
            'driver' => 'syslog',
            'level' => 'debug'
        ],

        'errorlog' => [
            'driver' => 'errorlog',
            'level' => 'debug'
        ],

        'rollbar' => [
            'driver' => 'monolog',
            'handler' => \Rollbar\Laravel\MonologHandler::class,
            'access_token' => env('ROLLBAR_TOKEN'),
            'level' => 'error',
            'person_fn' => 'get_rollbar_user'
        ]
    ]
];
