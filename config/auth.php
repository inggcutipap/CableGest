<?php

use App\Models\Usuario;

return [

    'defaults' => [
        'guard' => env('AUTH_GUARD', 'api'),
        'passwords' => env('AUTH_PASSWORD_BROKER', 'usuarios'),
    ],

    'guards' => [
        'api' => [
            'driver' => 'jwt',
            'provider' => 'usuarios',
        ],
    ],

    'providers' => [
        'usuarios' => [
            'driver' => 'eloquent',
            'model' => env('AUTH_MODEL', Usuario::class),
        ],
    ],

    'passwords' => [],

    'password_timeout' => env('AUTH_PASSWORD_TIMEOUT', 10800),

];
