<?php

namespace App\Core;

use ReflectionClass;
use ReflectionNamedType;

class Router
{
  private array $routes = [];
  private array $config;

  public function __construct(array $config)
  {
    $this->config = $config;
  }

  public function get(string $path, callable|array $handler): void
  {
    $this->routes['GET'][] = ['path' => $path, 'handler' => $handler];
  }

  public function post(string $path, callable|array $handler): void
  {
    $this->routes['POST'][] = ['path' => $path, 'handler' => $handler];
  }

  public function put(string $path, callable|array $handler): void
  {
    $this->routes['PUT'][] = ['path' => $path, 'handler' => $handler];
  }

  public function delete(string $path, callable|array $handler): void
  {
    $this->routes['DELETE'][] = ['path' => $path, 'handler' => $handler];
  }

  public function dispatch(string $path): void
  {
    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

    if (!isset($this->routes[$method])) {
      $this->send404("Route not found");
      return;
    }

    foreach ($this->routes[$method] as $route) {
      $paramNames = [];
      $pattern = $this->convertToRegex($route['path'], $paramNames);

      if (preg_match($pattern, $path, $matches)) {
        array_shift($matches);

        $params = [];
        foreach ($matches as $index => $value) {
          $params[$paramNames[$index]] = $value;
        }

        $this->executeHandler($route['handler'], $params);
        return;
      }
    }

    $this->send404("Route not found");
  }

  private function convertToRegex(string $route, array &$paramNames = []): string
  {
    $paramNames = [];

    $route = preg_replace_callback(
      '#\{(\w+)\}#',
      function ($matches) use (&$paramNames) {
        $paramNames[] = $matches[1];

        return match ($matches[1]) {
          'id' => '(\d+)',
          'slug' => '([a-z0-9\-]+)',
          'uuid' => '([a-f0-9\-]{36})',
          default => '([^/]+)',
        };
      },
      $route
    );

    return '#^' . rtrim($route, '/') . '/?$#';
  }

  private function executeHandler(callable|array $handler, array $params): void
  {
    try {
      if (is_array($handler)) {
        [$class, $method] = $handler;

        $controller = $this->resolveController($class);

        if (!method_exists($controller, $method)) {
          $this->send404("Handler method not found");
          return;
        }

        $controller->$method(...$params);
        return;
      }

      $handler(...$params);

    } catch (\Throwable $e) {
      http_response_code(500);
      echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
      ]);
    }
  }

  private function resolveController(string $class)
  {
    $reflection = new ReflectionClass($class);
    $constructor = $reflection->getConstructor();

    if (!$constructor) {
      return new $class();
    }

    $dependencies = [];

    foreach ($constructor->getParameters() as $param) {
      $type = $param->getType();

      if ($type instanceof ReflectionNamedType && !$type->isBuiltin()) {
        // It's a class — instantiate it with config
        $serviceClass = $type->getName();
        $dependencies[] = new $serviceClass($this->config);

      } elseif ($type instanceof ReflectionNamedType && $type->getName() === 'array') {
        // It's an array — pass config
        $dependencies[] = $this->config;

      } elseif ($param->isOptional()) {
        // Has default value — use it
        $dependencies[] = $param->getDefaultValue();
      }
    }

    return new $class(...$dependencies);
  }
  private function send404(string $message): void
  {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => $message]);
  }
}
