<?php

return [
    /*
     |--------------------------------------------------------------------------
     | Laravel CORS
     |--------------------------------------------------------------------------
     |
     | allowedOrigins, allowedHeaders and allowedMethods can be set to array('*')
     | to accept any value.
     |
     */

    'supportsCredentials' => true,
    'allowedOrigins' =>
        env('CORS_ALLOWED_ORIGINS') ? explode(',', env('CORS_ALLOWED_ORIGINS')) : ['*'],
    'allowedHeaders' => [
        'Cache-Control',
        'Content-Type',
        'X-Requested-With',
        'Authorization',
        'X-Auth-Token'
    ],
    'allowedMethods' => ['*'],
    'exposedHeaders' => ['Authorization', 'X-Auth-Token', 'Set-Cookie'],
    'maxAge' => 864000
];
