<?php

declare(strict_types=1);

namespace Pgs\Api;

final class Router
{
    /**
     * @var list<array{
     *   method: string,
     *   pattern: string,
     *   regex: string,
     *   handler: callable,
     *   middlewares: list<callable>
     * }>
     */
    private array $routes = [];

    /**
     * @param list<callable> $middlewares
     */
    public function add(string $method, string $pattern, callable $handler, array $middlewares = []): void
    {
        $normalizedPattern = '/' . trim($pattern, '/');
        if ($normalizedPattern === '') {
            $normalizedPattern = '/';
        }

        // Convert {param} or :param to named regex groups
        $regex = preg_replace('#\{([a-zA-Z0-9_]+)\}#', '(?P<$1>[^/]+)', $normalizedPattern);
        $regex = preg_replace('#:([a-zA-Z0-9_]+)#', '(?P<$1>[^/]+)', $regex);
        $regex = '#^' . $regex . '$#';

        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $normalizedPattern,
            'regex' => $regex,
            'handler' => $handler,
            'middlewares' => $middlewares,
        ];
    }

    public function dispatch(Request $r): Response
    {
        $method = $r->method();
        $path = $r->path();
        $pathMatchesAny = false;

        foreach ($this->routes as $route) {
            if (preg_match($route['regex'], $path, $matches)) {
                $pathMatchesAny = true;

                if ($route['method'] !== $method && $route['method'] !== 'ANY') {
                    continue;
                }

                // Extract named matches
                $params = [];
                foreach ($matches as $k => $v) {
                    if (is_string($k)) {
                        $params[$k] = $v;
                        $r = $r->withAttr($k, $v);
                    }
                }
                $r = $r->withAttr('params', $params);

                // Build middleware chain
                $pipeline = $route['middlewares'];
                $handler = $route['handler'];

                $runner = function (Request $req) use (&$runner, &$pipeline, $handler): Response {
                    if (empty($pipeline)) {
                        $res = $handler($req);
                        if (!$res instanceof Response) {
                            return Response::ok($res);
                        }
                        return $res;
                    }

                    $current = array_shift($pipeline);
                    return $current($req, $runner);
                };

                return $runner($r);
            }
        }

        if ($pathMatchesAny) {
            return Response::error(405, 'method_not_allowed', "Method {$method} not allowed for {$path}");
        }

        return Response::error(404, 'not_found', "Endpoint not found: {$method} {$path}");
    }
}
