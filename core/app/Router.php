<?php
declare(strict_types=1);
namespace Sysfera;

/** Routes like '/users/{id}'. Handlers receive (array $params) and either echo, return a string, or return null. */
final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, callable $handler, array $opt = []): void
    {
        $re = '~^' . preg_replace('~\{([a-z_]+)\*\}~', '(?P<$1>.+)', preg_replace('~\{([a-z_]+)\}~', '(?P<$1>[^/]+)', rtrim($pattern, '/') ?: '/')) . '/?$~';
        $this->routes[] = [strtoupper($method), $re, $handler, $opt];
    }

    /** @return array{0:callable,1:array,2:array}|null */
    public function match(string $method, string $path): ?array
    {
        $path = '/' . trim($path, '/');
        foreach ($this->routes as [$m, $re, $h, $opt]) {
            if ($m !== $method && $m !== '*') continue;
            if (preg_match($re, $path, $mm)) return [$h, array_filter($mm, 'is_string', ARRAY_FILTER_USE_KEY), $opt];
        }
        return null;
    }
}
