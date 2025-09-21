<?php

declare(strict_types=1);

namespace App\Core;


class Router
{
    /** @var array<string, array<int, array{pattern:string, regex:string, variables:array<int,string>, handler:callable|array}>> */
    private array $routes = [];

    public function get(string $pattern, callable|array $handler): self
    {
        return $this->addRoute('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable|array $handler): self
    {
        return $this->addRoute('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable|array $handler): self
    {
        return $this->addRoute('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable|array $handler): self
    {
        return $this->addRoute('DELETE', $pattern, $handler);
    }

    public function patch(string $pattern, callable|array $handler): self
    {
        return $this->addRoute('PATCH', $pattern, $handler);
    }

    public function options(string $pattern, callable|array $handler): self
    {
        return $this->addRoute('OPTIONS', $pattern, $handler);
    }

    private function addRoute(string $method, string $pattern, callable|array $handler): self
    {
        $method = strtoupper($method);
        if (!str_starts_with($pattern, '/')) {
            $pattern = '/' . ltrim($pattern, '/');
        }

        [$regex, $variables] = $this->compilePattern($pattern);

        $this->routes[$method][] = [
            'pattern' => $pattern,
            'regex' => $regex,
            'variables' => $variables,
            'handler' => $handler,
        ];

        return $this;
    }

    public function dispatch(Request $request): Response
    {
        $method = $request->getMethod();
        $path = rtrim($request->getPath(), '/') ?: '/';

        $candidates = $this->routes[$method] ?? [];

        if ($method === 'HEAD' && empty($candidates) && isset($this->routes['GET'])) {
            $candidates = $this->routes['GET'];
        }

        foreach ($candidates as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }

            $parameters = [];
            foreach ($route['variables'] as $name) {
                $parameters[] = is_numeric($matches[$name] ?? null)
                    ? (int) $matches[$name]
                    : ($matches[$name] ?? null);
            }

            $result = call_user_func_array($route['handler'], array_merge([$request], $parameters));

            $response = $this->normalizeResponse($result);

            if ($method === 'HEAD') {
                $response = $response->withoutBody();
            }

            return $response;
        }

        throw HttpException::notFound();
    }

    private function compilePattern(string $pattern): array
    {
        $variables = [];
        $escaped = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function (array $matches) use (&$variables) {
            $variables[] = $matches[1];
            return '(?P<' . $matches[1] . '>[^/]+)';
        }, $pattern);

        $regex = '#^' . rtrim($escaped, '/') . '/?$#';

        return [$regex, $variables];
    }

    private function normalizeResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if ($result === null) {
            return Response::json(['success' => true]);
        }

        if (is_array($result)) {
            return Response::json($result);
        }

        if (is_bool($result)) {
            return Response::json(['success' => $result]);
        }

        return new Response((string) $result);
    }
}

