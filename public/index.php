<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
header('Content-Type: application/json');

include_once __DIR__ . '/../pexpress/Pexpress.php';

$app = new Pexpress();

$app->use(function ($req, $next) {
  error_log("Request: " . $req['method'] . " " . $req['path']);
  echo "Logging middleware executed.\n";
  $next();
});

$authMiddleware = function ($req, $next) {
  if (!isset($_SESSION['user'])) {
    header("HTTP/1.0 401 Unauthorized");
    echo "Unauthorized";
    $req['_stop'] = true;
    return;
  }
  $next();
};

$app->get('/users/:id', function ($req) {
  echo "User ID: " . $req['params']['id'];
});

$app->get('/admin/dashboard', $authMiddleware, function ($req) {
  echo "Admin Dashboard";
});

$app->dispatch();
