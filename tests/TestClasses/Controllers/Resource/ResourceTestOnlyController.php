<?php

namespace Spatie\RouteDiscovery\Tests\TestClasses\Controllers\Resource;

use Illuminate\Http\Request;
use Spatie\RouteDiscovery\Attributes\Resource;

#[Resource('posts', only: ['index', 'store', 'show'])]
class ResourceTestOnlyController
{
    public function index()
    {
    }

    public function store(Request $request)
    {
    }

    public function show($id)
    {
    }
}
