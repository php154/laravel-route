<?php

use Spatie\RouteDiscovery\Tests\TestClasses\Controllers\PutTestController;

it('can register a put route', function () {
    $this->routeRegistrar->registerClass(PutTestController::class);

    $this
        ->assertRegisteredRoutesCount(1)
        ->assertRouteRegistered(PutTestController::class, 'myPutMethod', 'put', 'my-put-method');
});
