<?php

namespace Spatie\RouteAttributes\Tests\TestClasses\Controllers\RouteRegistrar;

use Spatie\RouteAttributes\Attributes\Get;

class RegistrarTestSecondController
{
    #[Get('second-method')]
    public function myMethod()
    {
    }
}
