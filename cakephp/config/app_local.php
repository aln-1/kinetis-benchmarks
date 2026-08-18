<?php

use function Cake\Core\env;

return [
    'debug' => filter_var(env('DEBUG', false), FILTER_VALIDATE_BOOLEAN),

    'Security' => [
        'salt' => env('SECURITY_SALT', 'e9c1c1baf5f89710810fd7a05e4c2367a1e2b3c4d5f60718293a4b5c6d7e8f90'),
    ],

    'Datasources' => [
        'default' => [
            'host' => env('DB_HOST', 'localhost'),
            'port' => env('DB_PORT', '3306'),
            'username' => env('DB_USER', 'my_app'),
            'password' => env('DB_PASSWORD', 'secret'),
            'database' => env('DB_NAME', 'my_app'),
            'url' => env('DATABASE_URL', null),
        ],
    ],
];
