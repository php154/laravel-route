<?php

namespace Spatie\RouteDiscovery;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Router;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use ReflectionAttribute;
use ReflectionClass;
use ReflectionMethod;
use ReflectionParameter;
use Spatie\RouteDiscovery\Attributes\Route;
use Spatie\RouteDiscovery\Attributes\RouteAttribute;
use Spatie\RouteDiscovery\Attributes\Where;
use Spatie\RouteDiscovery\Attributes\WhereAttribute;
use SplFileInfo;
use Symfony\Component\Finder\Finder;
use Throwable;

class RouteRegistrar
{
    private Router $router;

    protected string $basePath;

    protected string $rootNamespace;

    protected array $middleware = [];

    protected string $registeringDirectory = '';

    public function __construct(Router $router)
    {
        $this->router = $router;

        $this->basePath = app()->path();
    }

    public function useBasePath(string $basePath): self
    {
        $this->basePath = $basePath;

        return $this;
    }

    public function useRootNamespace(string $rootNamespace): self
    {
        $this->rootNamespace = $rootNamespace;

        return $this;
    }

    public function useMiddleware(string|array $middleware): self
    {
        $this->middleware = Arr::wrap($middleware);

        return $this;
    }

    public function middleware(): array
    {
        return $this->middleware ?? [];
    }

    public function registerDirectory(string|array $directories): void
    {
        $directories = Arr::wrap($directories);

        foreach ($directories as $directory) {
            $files = (new Finder())->files()->name('*.php')->in($directory);

            collect($files)->each(function (SplFileInfo $file) use ($directory) {
                $this->registeringDirectory = $directory;

                $this->registerFile($file);
            });
        }
    }

    public function registerFile(string|SplFileInfo $path): void
    {
        if (is_string($path)) {
            $path = new SplFileInfo($path);
        }

        $fullyQualifiedClassName = $this->fullQualifiedClassNameFromFile($path);

        $this->processAttributes($fullyQualifiedClassName);
    }

    public function registerClass(string $class): void
    {
        $this->processAttributes($class);
    }

    protected function fullQualifiedClassNameFromFile(SplFileInfo $file): string
    {
        $class = trim(Str::replaceFirst($this->basePath, '', $file->getRealPath()), DIRECTORY_SEPARATOR);

        $class = str_replace(
            [DIRECTORY_SEPARATOR, 'App\\'],
            ['\\', app()->getNamespace()],
            ucfirst(Str::replaceLast('.php', '', $class))
        );

        return $this->rootNamespace . $class;
    }

    protected function processAttributes(string $className): void
    {
        if (! class_exists($className)) {
            return;
        }

        $class = new ReflectionClass($className);

        $classRouteAttributes = new ClassRouteAttributes($class);

        if ($classRouteAttributes->resource()) {
            $this->registerResource($class, $classRouteAttributes);

            return;
        }

        $groups = $classRouteAttributes->groups();

        foreach ($groups as $group) {
            $router = $this->router;
            $router->group($group, fn () => $this->registerRoutes($class, $classRouteAttributes));
        }
    }

    protected function registerResource(ReflectionClass $class, ClassRouteAttributes $classRouteDiscovery): void
    {
        $this->router->group([
            'domain' => $classRouteDiscovery->domain(),
            'prefix' => $classRouteDiscovery->prefix(),
        ], function () use ($class, $classRouteDiscovery) {
            $route = $classRouteDiscovery->apiResource()
                ? $this->router->apiResource($classRouteDiscovery->resource(), $class->getName())
                : $this->router->resource($classRouteDiscovery->resource(), $class->getName());

            if ($only = $classRouteDiscovery->only()) {
                $route->only($only);
            }

            if ($except = $classRouteDiscovery->except()) {
                $route->except($except);
            }

            if ($names = $classRouteDiscovery->names()) {
                $route->names($names);
            }

            if ($middleware = $classRouteDiscovery->middleware()) {
                $route->middleware([...$this->middleware, ...$middleware]);
            }
        });
    }

    protected function registerRoutes(
        ReflectionClass      $class,
        ClassRouteAttributes $classRouteDiscovery
    ): void {
        if ($class->isAbstract()) {
            return;
        }

        foreach ($class->getMethods() as $method) {
            if (! $method->isPublic()) {
                continue;
            }

            $attributes = $method->getAttributes(RouteAttribute::class, ReflectionAttribute::IS_INSTANCEOF);
            $wheresAttributes = $method->getAttributes(WhereAttribute::class, ReflectionAttribute::IS_INSTANCEOF);

            if (! count($attributes)) {
                $attributes = [Route::new()];
            }

            foreach ($attributes as $attribute) {
                try {
                    $attributeClass = $attribute;

                    if ($attributeClass instanceof ReflectionAttribute) {
                        $attributeClass = $attribute->newInstance();
                    }
                } catch (Throwable $exception) {
                    continue;
                }

                if (! $attributeClass instanceof Route) {
                    $attributeClass = Route::new();
                }

                $uri = $attributeClass->uri;
                $httpMethods = $attributeClass->methods;

                if (! $uri) {
                    $uri = $this->discoverUri($class, $method);
                    $httpMethods = $this->discoverHttpMethods($class, $method);
                }

                if (! $uri) {
                    continue;
                }

                $action = $method->getName() === '__invoke'
                    ? $class->getName()
                    : [$class->getName(), $method->getName()];

                $route = $this->router
                    ->addRoute(
                        $httpMethods,
                        $uri,
                        $action,
                    )
                    ->name($attributeClass->name);

                $wheres = $classRouteDiscovery->wheres();
                foreach ($wheresAttributes as $wheresAttribute) {
                    /** @var Where $wheresAttributeClass */
                    $wheresAttributeClass = $wheresAttribute->newInstance();

                    // This also overrides class wheres if the same param is used
                    $wheres[$wheresAttributeClass->param] = $wheresAttributeClass->constraint;
                }
                if (! empty($wheres)) {
                    $route->setWheres($wheres);
                }

                $classMiddleware = $classRouteDiscovery->middleware();
                $methodMiddleware = $attributeClass->middleware;
                $route->middleware([...$this->middleware, ...$classMiddleware, ...$methodMiddleware]);
            }
        }
    }

    protected function discoverHttpMethods(ReflectionClass $class, ReflectionMethod $method): ?array
    {
        return match ($method->name) {
            'index', 'create', 'show', 'edit' => ['GET'],
            'store' => ['POST'],
            'update' => ['PUT', 'PATCH'],
            'destroy', 'delete' => ['DELETE'],
            default => ['GET'],
        };
    }

    protected function discoverUri(ReflectionClass $class, ReflectionMethod $method): ?string
    {
        $parts = Str::of($class->getFileName())
            ->after($this->registeringDirectory)
            ->beforeLast('Controller')
            ->explode('/');

        $uri = collect($parts)
            ->filter()
            ->map(fn (string $part) => Str::of($part)->kebab())
            ->implode('/');

        /** @var ReflectionParameter $modelParameter */
        $modelParameter = collect($method->getParameters())->first(function (ReflectionParameter $parameter) {
            return is_a($parameter->getType()?->getName(), Model::class, true);
        });

        if (! in_array($method->getName(), $this->commonControllerMethodNames())) {
            $uri .= '/' . Str::kebab($method->getName());
        }

        if ($modelParameter) {
            $uri .= "/{{$modelParameter->getName()}}";
        }

        return $uri;
    }

    protected function commonControllerMethodNames(): array
    {
        return ['index', '__invoke', 'get', 'show', 'create', 'store', 'edit', 'update', 'destroy', 'delete'];
    }
}
