<?php

namespace Spatie\RouteAttributes\RouteDiscovery;

use Spatie\RouteAttributes\RouteRegistrar;

class DiscoverControllers
{
    protected string $basePath = '';

    protected string $rootNamespace;

    public function __construct()
    {
        $this->rootNamespace = '';

        $this->basePath = base_path();
    }

    public function useRootNamespace(string $rootNamespace): self
    {
        $this->rootNamespace = $rootNamespace;

        return $this;
    }

    public function useBasePath(string $basePath): self
    {
        $this->basePath = $basePath;

        return $this;
    }

    public function in(string $directory)
    {
        $router = app()->router;

        (new RouteRegistrar($router))
            ->useRootNamespace($this->rootNamespace)
            ->useBasePath($this->basePath)
            ->registerDirectory($directory);
    }
}
