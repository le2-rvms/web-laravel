<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web',

    'middleware' => ['web'],

    'auth_middleware' => 'auth',

    'passwords' => 'users',

    'username' => 'email',

    'email' => 'email',

    'views' => true,

    'home' => '/web-admin',

    'prefix' => 'web-admin',

    'domain' => null,

    'lowercase_usernames' => false,

    'limiters' => [
        'login' => 'login',
    ],

    'paths' => [
        'login' => null,
        'logout' => null,
        'password' => [
            'request' => null,
            'reset' => null,
            'email' => null,
            'update' => null,
            'confirm' => null,
            'confirmation' => null,
        ],
        'register' => null,
        'verification' => [
            'notice' => null,
            'verify' => null,
            'send' => null,
        ],
        'user-profile-information' => [
            'update' => null,
        ],
        'user-password' => [
            'update' => null,
        ],
        'two-factor' => [
            'login' => null,
            'enable' => null,
            'confirm' => null,
            'disable' => null,
            'qr-code' => null,
            'secret-key' => null,
            'recovery-codes' => null,
        ],
    ],

    'redirects' => [
        'login' => '/web-admin',
        'logout' => '/web-admin/login',
        'password-confirmation' => null,
        'register' => null,
        'email-verification' => null,
        'password-reset' => '/web-admin/login',
    ],

    'features' => [
        Features::resetPasswords(),
    ],
];
