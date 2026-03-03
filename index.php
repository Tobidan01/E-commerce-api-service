<?php
declare(strict_types=1);
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Router;
use App\Helpers\Response;
use App\Helpers\JwtAuth;

// Load config (handles .env locally + getenv on Render)
$config = require __DIR__ . '/config.php';

// Define constants
define('GOOGLE_CLIENT_ID', $config['GOOGLE_CLIENT_ID'] ?? '');
define('JWT_SECRET', $config['JWT_SECRET'] ?? 'change_me');
define('PAYSTACK_SECRET_KEY', $config['PAYSTACK_SECRET_KEY'] ?? '');

// Initialize JWT
JwtAuth::init($config);

// Detect environment
$isLocal = in_array(
  $_SERVER['SERVER_NAME'] ?? '',
  ['localhost', '127.0.0.1']
);

// Project prefix for local only
$PROJECT_PREFIX = $isLocal ? '/php-class/E-COMMERCE' : '';

// Create router
$router = new Router($config);

// Get request path
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';

// Remove prefix if present
if ($PROJECT_PREFIX && str_starts_with($requestUri, $PROJECT_PREFIX)) {
  $requestUri = substr($requestUri, strlen($PROJECT_PREFIX));
}

// Normalize path
$path = '/' . trim(str_replace('/index.php', '', $requestUri), '/');

// Only allow API routes
if (!str_starts_with($path, '/api/')) {
  Response::json(false, 'Not an API request');
  exit;
}

// Load routes
require __DIR__ . '/routes/authroutes.php';
require __DIR__ . '/routes/productroutes.php';
require __DIR__ . '/routes/categoryroutes.php';
require __DIR__ . '/routes/cartroutes.php';
require __DIR__ . '/routes/orderroutes.php';
require __DIR__ . '/routes/wishlistroutes.php';
require __DIR__ . '/routes/paymentroutes.php';

// Dispatch
$router->dispatch($path);