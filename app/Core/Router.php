<?php declare(strict_types=1);
/**
 * @file app/Core/Router.php
 * @description 轻量路由器：精确路径 + {param} 占位符，按 [控制器类, 方法] 分发
 * @author AI
 * @version 0.6
 * @date 2026-09-12
 */

namespace App\Core;

final class Router
{
    /** @var array<int, array{0:string, 1:string, 2:array{0:string, 1:string}}> */
    private array $routes = [];

    /** @var callable|null */
    private $notFound = null;

    public function get(string $pattern, array $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, array $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, array $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, array $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function onNotFound(callable $callback): void
    {
        $this->notFound = $callback;
    }

    private function add(string $method, string $pattern, array $handler): void
    {
        $regex = '#^' . preg_replace('#\{([a-zA-Z_][a-zA-Z0-9_]*)\}#', '(?P<$1>[^/]+)', $pattern) . '$#';
        $this->routes[] = [$method, $regex, $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        foreach ($this->routes as [$routeMethod, $regex, $handler]) {
            if ($routeMethod !== $method || !preg_match($regex, $path, $matches)) {
                continue;
            }
            [$class, $action] = $handler;
            // 仅保留命名捕获组作为路由参数
            $params = array_values(array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY));
            (new $class())->{$action}(...$params);
            return;
        }
        // 兜底 404：声明为 never 就必须真正终止，否则会因隐式返回触发 TypeError
        ($this->notFound ?? static function (): never {
            http_response_code(404);
            exit;
        })();
    }
}
