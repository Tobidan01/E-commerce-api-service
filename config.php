<?php
declare(strict_types=1);

// Load .env for local development only
if (file_exists(__DIR__ . '/.env')) {
  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
  $dotenv->safeLoad();

  foreach ($_ENV as $key => $value) {
    putenv("$key=$value");
  }
}

$env = getenv('APP_ENV') ?: 'production';
$databaseUrl = getenv('DATABASE_URL') ?: null;

// Parse DATABASE_URL if provided (Render/Heroku)
if ($databaseUrl) {
  $parts = parse_url($databaseUrl);
  $dbHost = $parts['host'] ?? '';
  $dbPort = (int) ($parts['port'] ?? 3306);
  $dbName = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
  $dbUser = $parts['user'] ?? '';
  $dbPass = $parts['pass'] ?? '';
} else {
  $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
  $dbPort = (int) (getenv('DB_PORT') ?: 3306);   // ✅ cast to int
  $dbName = getenv('DB_NAME') ?: '';
  $dbUser = getenv('DB_USER') ?: '';
  $dbPass = getenv('DB_PASS') ?: '';
}

// JWT secret must be set in production
$jwtSecret = getenv('JWT_SECRET') ?: '';
if (empty($jwtSecret) && $env === 'production') {
  error_log('WARNING: JWT_SECRET is not set!');
}

return [
  // App
  'APP_ENV' => $env,
  'APP_URL' => getenv('APP_URL') ?: '',

  // Database
  'DATABASE_URL' => $databaseUrl,
  'DB_HOST' => $dbHost,
  'DB_PORT' => $dbPort,
  'DB_NAME' => $dbName,
  'DB_USER' => $dbUser,
  'DB_PASS' => $dbPass,
  'DB_CHARSET' => getenv('DB_CHARSET') ?: 'utf8mb4',

  // JWT
  'JWT_SECRET' => $jwtSecret,
  'JWT_EXPIRES_IN' => (int) (getenv('JWT_EXPIRES_IN') ?: 3600), // ✅ cast to int

  // Google OAuth
  'GOOGLE_CLIENT_ID' => getenv('GOOGLE_CLIENT_ID') ?: '',

  // Paystack
  'PAYSTACK_SECRET_KEY' => getenv('PAYSTACK_SECRET_KEY') ?: '',
  'PAYSTACK_PUBLIC_KEY' => getenv('PAYSTACK_PUBLIC_KEY') ?: '',
];