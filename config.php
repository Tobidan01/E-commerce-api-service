<?php
declare(strict_types=1);

// Load .env for local development
if (file_exists(__DIR__ . '/.env')) {
  $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
  $dotenv->safeLoad();

  // Push to getenv so our config can read them
  foreach ($_ENV as $key => $value) {
    putenv("$key=$value");
  }
}

$env = getenv('APP_ENV') ?: 'production';
$databaseUrl = getenv('DATABASE_URL') ?: null;

if ($databaseUrl) {
  $parts = parse_url($databaseUrl);
  $dbHost = $parts['host'] ?? '';
  $dbPort = $parts['port'] ?? 3306;
  $dbName = isset($parts['path']) ? ltrim($parts['path'], '/') : '';
  $dbUser = $parts['user'] ?? '';
  $dbPass = $parts['pass'] ?? '';
} else {
  $dbHost = getenv('DB_HOST') ?: '127.0.0.1';
  $dbPort = getenv('DB_PORT') ?: 3306;
  $dbName = getenv('DB_NAME') ?: '';
  $dbUser = getenv('DB_USER') ?: '';
  $dbPass = getenv('DB_PASS') ?: '';
}

return [
  'APP_ENV' => $env,
  'DB_HOST' => $dbHost,
  'DB_PORT' => $dbPort,
  'DB_NAME' => $dbName,
  'DB_USER' => $dbUser,
  'DB_PASS' => $dbPass,
  'DB_CHARSET' => getenv('DB_CHARSET') ?: 'utf8mb4',
  'JWT_SECRET' => getenv('JWT_SECRET') ?: '',
  'JWT_EXPIRES_IN' => getenv('JWT_EXPIRES_IN') ?: 3600,
  'APP_URL' => getenv('APP_URL') ?: '',
  'DATABASE_URL' => $databaseUrl,
  'GOOGLE_CLIENT_ID' => getenv('GOOGLE_CLIENT_ID') ?: '',
  'PAYSTACK_SECRET_KEY' => getenv('PAYSTACK_SECRET_KEY') ?: '',
  'PAYSTACK_PUBLIC_KEY' => getenv('PAYSTACK_PUBLIC_KEY') ?: '',
];