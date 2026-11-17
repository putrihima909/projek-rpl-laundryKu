<?php

namespace App\Core;

use App\Helpers\Response;

abstract class Controller
{
    protected function view(string $viewPath, array $data = [], string $layout = 'main'): void
    {
        extract($data);

        $viewFile = __DIR__ . '/../Views/' . str_replace('.', '/', $viewPath) . '.php';
        if (!file_exists($viewFile)) {
            throw new \Exception("View file [{$viewPath}] not found at {$viewFile}");
        }

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout) {
            $layoutFile = __DIR__ . '/../Views/layouts/' . $layout . '.php';
            if (file_exists($layoutFile)) {
                require $layoutFile;
            } else {
                echo $content;
            }
        } else {
            echo $content;
        }
    }

    protected function json(mixed $data, int $statusCode = 200): void
    {
        Response::json($data, $statusCode);
    }

    protected function redirect(string $path): void
    {
        $baseUrl = config('app.url', 'http://localhost/rpl/public');
        $target = str_starts_with($path, 'http') ? $path : rtrim($baseUrl, '/') . '/' . ltrim($path, '/');
        Response::redirect($target);
    }
}
