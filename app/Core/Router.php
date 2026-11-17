<?php

namespace App\Core;

use Exception;

class Router
{
    private array $routes = [];

    public function get(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('GET', $path, $handler, $middlewares);
    }

    public function post(string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->addRoute('POST', $path, $handler, $middlewares);
    }

    private function addRoute(string $method, string $path, callable|array $handler, array $middlewares = []): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'path' => rtrim($path, '/') ?: '/',
            'handler' => $handler,
            'middlewares' => $middlewares
        ];
    }

    public function dispatch(string $requestUri, string $requestMethod): void
    {
        // Clean request URI by stripping query params
        $uri = parse_url($requestUri, PHP_URL_PATH);
        
        // Remove subfolder prefix if hosted in subfolder (e.g., /rpl/public)
        $scriptName = dirname($_SERVER['SCRIPT_NAME']);
        if ($scriptName !== '/' && $scriptName !== '\\' && str_starts_with($uri, $scriptName)) {
            $uri = substr($uri, strlen($scriptName));
        }

        $uri = rtrim($uri, '/') ?: '/';
        $method = strtoupper($requestMethod);

        // 1. First Pass: Exact Static Routes match first (prevents dynamic wildcards from masking static endpoints)
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (!str_contains($route['path'], '{') && $route['path'] === $uri) {
                $this->executeRoute($route, []);
                return;
            }
        }

        // 2. Second Pass: Dynamic Parameterized Routes
        foreach ($this->routes as $route) {
            if ($route['method'] !== $method) {
                continue;
            }

            if (str_contains($route['path'], '{')) {
                // Compile route pattern:
                // - Supports explicit regex: {id:\d+} or {slug:[a-z0-9-]+}
                // - Parameter 'id' or parameters ending with '_id' strictly enforce digits (\d+)
                // - Generic parameters default to non-slash characters ([^/]+)
                $pattern = preg_replace_callback('#\{([a-zA-Z0-9_]+)(?::([^}]+))?\}#', function ($matches) {
                    $paramName = $matches[1];
                    $customRegex = $matches[2] ?? null;
                    if ($customRegex !== null) {
                        return '(?P<' . $paramName . '>' . $customRegex . ')';
                    }
                    if ($paramName === 'id' || str_ends_with($paramName, '_id')) {
                        return '(?P<' . $paramName . '>\d+)';
                    }
                    return '(?P<' . $paramName . '>[^/]+)';
                }, $route['path']);

                if (preg_match('#^' . $pattern . '$#', $uri, $matches)) {
                    $params = [];
                    foreach ($matches as $k => $v) {
                        if (!is_int($k)) {
                            // Cast numeric string to integer if all digits for type-hinted controller methods
                            $params[$k] = ctype_digit($v) ? (int)$v : $v;
                            $_GET[$k] = $v;
                        }
                    }

                    $this->executeRoute($route, $params);
                    return;
                }
            }
        }

        // 404 Not Found Page
        http_response_code(404);
        $baseUrl = config('app.url', 'http://localhost/rpl/public');
        echo "<!DOCTYPE html>
        <html>
        <head>
            <meta charset='utf-8'>
            <title>404 Not Found</title>
            <link rel='stylesheet' href='https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css'>
        </head>
        <body class='bg-light d-flex align-items-center justify-content-center vh-100'>
            <div class='text-center'>
                <h1 class='display-1 fw-bold text-primary'>404</h1>
                <p class='fs-3'>Halaman Tidak Ditemukan</p>
                <p class='lead'>Halaman yang Anda cari tidak ada atau telah dipindahkan.</p>
                <a href='{$baseUrl}/' class='btn btn-primary btn-lg rounded-pill px-4'>Kembali ke Beranda</a>
            </div>
        </body>
        </html>";
    }

    private function executeRoute(array $route, array $params): void
    {
        // Execute Middlewares
        foreach ($route['middlewares'] as $middleware) {
            if (is_string($middleware)) {
                $middlewareInstance = new $middleware();
                if (!$middlewareInstance->handle()) {
                    return;
                }
            } elseif ($middleware instanceof Middleware) {
                if (!$middleware->handle()) {
                    return;
                }
            }
        }

        // Execute Handler
        $handler = $route['handler'];
        if (is_callable($handler)) {
            call_user_func_array($handler, $params);
            return;
        }

        if (is_array($handler) && count($handler) === 2) {
            $controllerClass = $handler[0];
            $action = $handler[1];

            if (class_exists($controllerClass)) {
                $controller = new $controllerClass();
                if (method_exists($controller, $action)) {
                    $controller->$action(...$params);
                    return;
                }
            }
        }
    }
}

