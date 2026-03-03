<?php

namespace App\Config;

use PDO;
use PDOException;

class Database
{
  private static ?PDO $connection = null;

  public function __construct(private array $config = [])
  {
    // If no config passed, load from environment directly
    if (empty($this->config)) {
      $this->config = $this->loadFromEnv();
    }
  }

  public function connect(): PDO
  {
    if (self::$connection !== null) {
      return self::$connection;
    }

    $host = $this->config['DB_HOST'] ?? '';
    $db = $this->config['DB_NAME'] ?? '';
    $user = $this->config['DB_USER'] ?? '';
    $pass = $this->config['DB_PASS'] ?? '';
    $charset = $this->config['DB_CHARSET'] ?? 'utf8mb4';
    $port = $this->config['DB_PORT'] ?? 3306;

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=$charset";

    $options = [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ];

    try {
      self::$connection = new PDO($dsn, $user, $pass, $options);
      return self::$connection;
    } catch (PDOException $e) {
      error_log("DB Connection Error: " . $e->getMessage());
      throw new PDOException("Database connection failed: " . $e->getMessage());
    }
  }

  private function loadFromEnv(): array
  {
    // Support DATABASE_URL (Render, Heroku etc)
    $databaseUrl = getenv('DATABASE_URL') ?: null;

    if ($databaseUrl) {
      $parts = parse_url($databaseUrl);
      return [
        'DB_HOST' => $parts['host'] ?? '',
        'DB_PORT' => $parts['port'] ?? 3306,
        'DB_NAME' => isset($parts['path']) ? ltrim($parts['path'], '/') : '',
        'DB_USER' => $parts['user'] ?? '',
        'DB_PASS' => $parts['pass'] ?? '',
        'DB_CHARSET' => 'utf8mb4',
        'APP_ENV' => getenv('APP_ENV') ?: 'production'
      ];
    }

    return [
      'DB_HOST' => getenv('DB_HOST') ?: '127.0.0.1',
      'DB_PORT' => getenv('DB_PORT') ?: 3306,
      'DB_NAME' => getenv('DB_NAME') ?: '',
      'DB_USER' => getenv('DB_USER') ?: '',
      'DB_PASS' => getenv('DB_PASS') ?: '',
      'DB_CHARSET' => getenv('DB_CHARSET') ?: 'utf8mb4',
      'APP_ENV' => getenv('APP_ENV') ?: 'production'
    ];
  }

  // Reset connection (useful for testing)
  public static function reset(): void
  {
    self::$connection = null;
  }
}