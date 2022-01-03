<?php

namespace Spatie\RouteDiscovery\NodeTree;

use function collect;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use ReflectionMethod;
use ReflectionParameter;
use Spatie\RouteDiscovery\Attributes\WhereAttribute;

class Action
{
    public ReflectionMethod $method;
    public string $uri;
    /** @var array<int, string>  */
    public array $methods = [];

    /** @var array{class-string, string}  */
    public array $action;

    /** @var array<int, class-string>  */
    public array $middleware = [];

    /** @var array<int, \Spatie\RouteDiscovery\Attributes\WhereAttribute> */
    public array $wheres = [];
    public ?string $name = null;

    /**
     * @param ReflectionMethod $method
     * @param class-string $controllerClass
     */
    public function __construct(ReflectionMethod $method, string $controllerClass)
    {
        $this->method = $method;

        $this->uri = $this->relativeUri();

        $this->methods = $this->discoverHttpMethods();

        $this->action = [$controllerClass, $method->name];
    }

    public function relativeUri(): string
    {
        /** @var ReflectionParameter $modelParameter */
        $modelParameter = collect($this->method->getParameters())->first(function (ReflectionParameter $parameter) {
            /** @phpstan-ignore-next-line */
            return is_a($parameter->getType()?->getName(), Model::class, true);
        });

        $uri = '';

        if (! in_array($this->method->getName(), $this->commonControllerMethodNames())) {
            $uri = Str::kebab($this->method->getName());
        }

        /** @phpstan-ignore-next-line */
        if ($modelParameter) {
            if ($uri !== '') {
                $uri .= '/';
            }

            $uri .= "{{$modelParameter->getName()}}";
        }

        return $uri;
    }

    /**
     * @return array<int, string>
     */
    protected function discoverHttpMethods(): array
    {
        return match ($this->method->name) {
            'index', 'create', 'show', 'edit' => ['GET'],
            'store' => ['POST'],
            'update' => ['PUT', 'PATCH'],
            'destroy', 'delete' => ['DELETE'],
            default => ['GET'],
        };
    }

    /** @return array<int, string> */
    protected function commonControllerMethodNames(): array
    {
        return [
            'index', '__invoke', 'get',
            'show', 'store', 'update',
            'destroy', 'delete',
        ];
    }
}
