<?php

namespace Spatie\RouteDiscovery\Tests\TestClasses\Discovery\OverrideHttpMethod;

use Illuminate\Foundation\Auth\User;
use Spatie\RouteDiscovery\Attributes\Route;

class OverrideHttpMethodController
{
    #[Route(method: 'delete')]
    public function edit(User $user)
    {

    }
}
