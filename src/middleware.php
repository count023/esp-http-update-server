<?php
// Application middleware

use \com\gpioneers\HttpBasicAuthentication\ArrayHashedPasswordsAuthenticator;

require __DIR__ . '/admin-users.php';

$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "path" => "/admin.*",
    "realm" => "Protected",
    "secure" => false, // allow http instead of https!
    // "users" => $authorizedAdminUsers
    "authenticator" => new ArrayHashedPasswordsAuthenticator([
        'users' => $authorizedAdminUsers,
        'logger' => $container['logger']
    ])
]));

require __DIR__ . '/device-users.php';
$app->add(new \Slim\Middleware\HttpBasicAuthentication([
    "path" => "/device/authenticate/.*",
    "realm" => "Protected",
    "secure" => false, // allow http instead of https!
    "users" => $authorizedDevices
]));
