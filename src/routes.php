<?php

// Pages without authentication
$app->group('/_p', function() {
    $this->map(['GET', 'POST'], '/auth/login', \Patisserie\Controllers\AuthController::class . ':login')
        ->setName('login');
    $this->map(['GET', 'POST'], '/configure/password', \Patisserie\Controllers\ConfigureController::class . ':password')
        ->setName('configure_password');
    $this->map(['GET', 'POST'], '/xmlrpc', \Patisserie\Controllers\XmlRpcController::class . ':index')
        ->setName('xmlrpc');
});

// Pages within authentication
$app->group('/_p', function () {
    $this->map(['GET'], '/auth/logout', \Patisserie\Controllers\AuthController::class . ':logout')
        ->setName('logout');
    $this->map(['GET'], '/browse', \Patisserie\Controllers\BrowseEntryController::class . ':browse')
        ->setName('browse_entry');
    $this->map(['GET', 'POST'], '/edit', \Patisserie\Controllers\EditEntryController::class . ':edit')
        ->setName('edit_entry');
    $this->map(['GET', 'POST'], '/new', \Patisserie\Controllers\NewEntryController::class . ':new')
        ->setName('new_entry');
})
    ->add(new Patisserie\Middleware\AuthMiddleware($container))
    ->add($container->get('csrf'));
