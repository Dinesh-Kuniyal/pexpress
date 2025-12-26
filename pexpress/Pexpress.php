<?php
include_once __DIR__ . '/config.php';

class RouteNode
{
  public $children = [];
  public $handler = null;
  public $middlewares = [];
  public $params = [];
  public $isParam = false;
  public $paramName = null;
}

class Pexpress
{
  private $routeTrees = [];
  private $globalMiddlewares = [];

  public function __construct()
  {
    $this->routeTrees = [
      'GET' => new RouteNode(),
      'POST' => new RouteNode(),
      'PUT' => new RouteNode(),
      'DELETE' => new RouteNode(),
      'PATCH' => new RouteNode(),
    ];
  }

  public function use($middleware): void
  {
    if (is_callable($middleware)) {
      $this->globalMiddlewares[] = $middleware;
    }
  }

  private function addRoute($method, $path, ...$args): string
  {
    if (!isset($this->routeTrees[$method])) {
      $this->routeTrees[$method] = new RouteNode();
    }

    $handler = array_pop($args);
    $middlewares = [];

    foreach ($args as $arg) {
      if (is_array($arg)) {
        $middlewares = array_merge($middlewares, $arg);
      } elseif (is_callable($arg)) {
        $middlewares[] = $arg;
      }
    }

    $segments = $this->parsePath($path);
    $node = $this->routeTrees[$method];

    foreach ($segments as $segment) {
      $isParam = strpos($segment, ':') === 0;
      $key = $isParam ? ':param' : $segment;

      if (!isset($node->children[$key])) {
        $node->children[$key] = new RouteNode();

        if ($isParam) {
          $node->children[$key]->isParam = true;
          $node->children[$key]->paramName = substr($segment, 1);
        }
      }

      $node = $node->children[$key];
    }

    $node->handler = $handler;
    $node->middlewares = $middlewares;

    return "Route added: [$method] $path";
  }

  private function parsePath($path): array
  {
    $path = trim($path, '/');
    return $path === '' ? [] : explode('/', $path);
  }

  private function findRoute($method, $path): ?array
  {
    if (!isset($this->routeTrees[$method])) {
      return null;
    }

    $segments = $this->parsePath($path);
    $node = $this->routeTrees[$method];
    $params = [];

    foreach ($segments as $segment) {
      $matched = false;

      if (isset($node->children[$segment])) {
        $node = $node->children[$segment];
        $matched = true;
      } elseif (isset($node->children[':param'])) {
        $node = $node->children[':param'];
        $params[$node->paramName] = $segment;
        $matched = true;
      }

      if (!$matched) {
        return null;
      }
    }

    if ($node->handler === null) {
      return null;
    }

    return [
      'handler' => $node->handler,
      'middlewares' => $node->middlewares,
      'params' => $params
    ];
  }

  private function executeMiddlewares($middlewares, &$request): bool
  {
    foreach ($middlewares as $middleware) {
      $nextCalled = false;

      call_user_func($middleware, $request, function () use (&$nextCalled) {
        $nextCalled = true;
      });

      if ((isset($request['_stop']) && $request['_stop']) || !$nextCalled) {
        return false;
      }
    }

    return true;
  }

  public function get($path, ...$args): string
  {
    return $this->addRoute("GET", $path, ...$args);
  }

  public function post($path, ...$args): string
  {
    return $this->addRoute("POST", $path, ...$args);
  }

  public function put($path, ...$args): string
  {
    return $this->addRoute("PUT", $path, ...$args);
  }

  public function delete($path, ...$args): string
  {
    return $this->addRoute("DELETE", $path, ...$args);
  }

  public function patch($path, ...$args): string
  {
    return $this->addRoute("PATCH", $path, ...$args);
  }

  public function dispatch(): void
  {
    $requestMethod = $_SERVER['REQUEST_METHOD'];
    $requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

    global $exlucedPath;
    if (isset($exlucedPath) && strpos($requestUri, $exlucedPath) === 0) {
      $requestUri = substr($requestUri, strlen($exlucedPath));
    }

    $match = $this->findRoute($requestMethod, $requestUri);

    if ($match === null) {
      header("HTTP/1.0 404 Not Found");
      echo "404 Not Found";
      return;
    }

    $request = array_merge($_REQUEST, [
      'params' => $match['params'],
      'method' => $requestMethod,
      'path' => $requestUri
    ]);

    if (!$this->executeMiddlewares($this->globalMiddlewares, $request)) {
      return;
    }

    if (!$this->executeMiddlewares($match['middlewares'], $request)) {
      return;
    }

    call_user_func($match['handler'], $request);
  }
}
