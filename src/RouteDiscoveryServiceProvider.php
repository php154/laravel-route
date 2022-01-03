<?php

namespace Spatie\RouteDiscovery;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Spatie\RouteDiscovery\Discovery\Discover;

class RouteDiscoveryServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-route-discovery')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        if ($this->app->routesAreCached()) {
            return;
        }

        $this
            ->registerRoutesForControllers()
            ->registerRoutesForViews();
    }

    public function registerRoutesForControllers(): self
    {
        collect(config('route-discovery.discover_controllers_in_directory'))
            ->each(
                fn (string $directory) => Discover::controllers()->in($directory)
            );

        return $this;
    }

    public function registerRoutesForViews(): self
    {
        collect(config('route-discovery.discover_views_in_directory'))
            ->each(function (array|string $directories, int|string $prefix) {
                if (is_numeric($prefix)) {
                    $prefix = '';
                }

                $directories = Arr::wrap($directories);

                foreach ($directories as $directory) {
                    Route::prefix($prefix)->group(function () use ($directory) {
                        Discover::views()->in($directory);
                    });
                }
            });

        return $this;
    }
}
