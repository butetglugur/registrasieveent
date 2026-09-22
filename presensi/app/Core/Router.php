<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private static ?Router $instance = null;

    /** @var array<int,array{method:string,path:string,regex:string,action:mixed,middleware:string[],name:?string}> */
    private array $routes = [];
    /** @var array<string,int> */
    private array $named = [];
    private string $prefix = '';
    /** @var string[] */
    private array $middleware = [];

    public static function instance(): Router
    {
        if (self::$instance === null) {
            self::$instance = new Router();
        }
        return self::$instance;
    }

    public static function fresh(): Router
    {
        return self::$instance = new Router();
    }

    public function get(string $path, $action): Router
    {
        return $this->add('GET', $path, $action);
    }

    public function post(string $path, $action): Router
    {
        return $this->add('POST', $path, $action);
    }

    public function add(string $method, string $path, $action): Router
    {
        $full = '/' . trim($this->prefix . '/' . trim($path, '/'), '/');
        $regex = '#^' . preg_replace_callback(
            '/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            static fn(array $m) => '(?P<' . $m[1] . '>[^/]+)',
            str_replace('.', '\.', $full)
        ) . '$#u';
        $this->routes[] = [
            'method'     => $method,
            'path'       => $full,
            'regex'      => $regex,
            'action'     => $action,
            'middleware' => $this->middleware,
            'name'       => null,
        ];
        return $this;
    }

    /** Beri nama route terakhir. */
    public function name(string $name): Router
    {
        $idx = count($this->routes) - 1;
        $this->routes[$idx]['name'] = $name;
        $this->named[$name] = $idx;
        return $this;
    }

    /** @param array{prefix?:string,middleware?:string[]} $attrs */
    public function group(array $attrs, callable $callback): void
    {
        $prevPrefix = $this->prefix;
        $prevMw = $this->middleware;
        $this->prefix = rtrim($prevPrefix . '/' . trim($attrs['prefix'] ?? '', '/'), '/');
        $this->middleware = array_merge($prevMw, $attrs['middleware'] ?? []);
        $callback($this);
        $this->prefix = $prevPrefix;
        $this->middleware = $prevMw;
    }

    /**
     * @return array{0:array<string,mixed>,1:array<string,string>}
     */
    public function match(string $method, string $path): array
    {
        $allowed = false;
        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $m)) {
                continue;
            }
            if ($route['method'] !== $method) {
                $allowed = true;
                continue;
            }
            $params = [];
            foreach ($m as $k => $v) {
                if (is_string($k)) {
                    $params[$k] = $v;
                }
            }
            return [$route, $params];
        }
        throw new HttpException($allowed ? 405 : 404);
    }

    public function pathFor(string $name, array $params = []): string
    {
        if (!isset($this->named[$name])) {
            throw new \InvalidArgumentException('Route tidak dikenal: ' . $name);
        }
        $path = $this->routes[$this->named[$name]]['path'];
        return (string) preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', static function (array $m) use ($params) {
            if (!array_key_exists($m[1], $params)) {
                throw new \InvalidArgumentException('Parameter route hilang: ' . $m[1]);
            }
            return rawurlencode((string) $params[$m[1]]);
        }, $path);
    }

    public function routes(): array
    {
        return $this->routes;
    }
}
