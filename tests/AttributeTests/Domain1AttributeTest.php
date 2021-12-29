<?php

declare(strict_types=1);

use Spatie\RouteAttributes\Tests\TestClasses\Controllers\Domain1TestController;
use Spatie\RouteAttributes\Tests\TestClasses\Controllers\Domain2TestController;

it('registers the same url on different domains', function () {
    config()->set('domains.test', 'config.localhost');
    config()->set('domains.test2', 'config2.localhost');
    $this->routeRegistrar->registerClass(Domain1TestController::class);
    $this->routeRegistrar->registerClass(Domain2TestController::class);

    $this
        ->assertRegisteredRoutesCount(2)
        ->assertRouteRegistered(
            Domain1TestController::class,
            controllerMethod: 'myGetMethod',
            uri: 'my-get-method',
            domain: 'config.localhost'
        )
        ->assertRouteRegistered(
            Domain2TestController::class,
            controllerMethod: 'myGetMethod',
            uri: 'my-get-method',
            domain: 'config2.localhost'
        );
});
