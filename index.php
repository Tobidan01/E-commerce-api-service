<?php
declare(strict_types=1);

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/vendor/autoload.php';

use App\Core\Router;
use App\Helpers\Response;
use App\Helpers\JwtAuth;
use App\Helpers\ErrorHandler;

// ✅ ADD THIS BLOCK RIGHT HERE (BEFORE EVERYTHING ELSE!)
set_error_handler(function ($errno, $errstr, $errfile, $errline) {
  throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
});

set_exception_handler(function (\Throwable $e) {
  ErrorHandler::handle($e);
  exit;
});

// -----------------------------
// Load Config FIRST (CRITICAL)
// -----------------------------
$config = require __DIR__ . '/config.php';

// -----------------------------
// Headers (CORS + JSON)
// -----------------------------
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With");
header("Content-Type: application/json");

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
  http_response_code(200);
  exit;
}

// -----------------------------
// Define constants (fallback safety)
// -----------------------------
define('GOOGLE_CLIENT_ID', $config['GOOGLE_CLIENT_ID'] ?? '');
define('JWT_SECRET', $config['JWT_SECRET'] ?? 'change_me');
define('PAYSTACK_SECRET_KEY', $config['PAYSTACK_SECRET_KEY'] ?? '');

// -----------------------------
// Initialize JWT (AFTER config)
// -----------------------------
JwtAuth::init($config);

// -----------------------------
// Detect environment
// -----------------------------
$isLocal = in_array(
  $_SERVER['SERVER_NAME'] ?? '',
  ['localhost', '127.0.0.1']
);

// Project prefix (LOCAL ONLY)
$PROJECT_PREFIX = $isLocal ? '/php-class/E-COMMERCE' : '';

// -----------------------------
// Create Router (PASS CONFIG)
// -----------------------------
$router = new Router($config);

// -----------------------------
// Parse Request URI
// -----------------------------
$requestUri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';

// Remove local prefix
if ($PROJECT_PREFIX && str_starts_with($requestUri, $PROJECT_PREFIX)) {
  $requestUri = substr($requestUri, strlen($PROJECT_PREFIX));
}

// Normalize path
$path = '/' . trim(str_replace('/index.php', '', $requestUri), '/');

// Debug log (AFTER processing)
error_log("REQUEST: " . $_SERVER['REQUEST_METHOD'] . " " . $path);

// -----------------------------
// Enforce API prefix
// -----------------------------
if (!str_starts_with($path, '/api/')) {
  Response::json(false, 'Not an API request', 404);
  exit;
}

// -----------------------------
// Load Routes (ALL USE $router)
// -----------------------------
require __DIR__ . '/routes/authroutes.php';
require __DIR__ . '/routes/productroutes.php';
require __DIR__ . '/routes/categoryroutes.php';
require __DIR__ . '/routes/cartroutes.php';
require __DIR__ . '/routes/orderroutes.php';
require __DIR__ . '/routes/wishlistroutes.php';
require __DIR__ . '/routes/paymentroutes.php';
require __DIR__ . '/routes/adminroutes.php';

// -----------------------------
// Dispatch Request
// -----------------------------
$router->dispatch($path);