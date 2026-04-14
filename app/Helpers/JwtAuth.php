<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;

class JwtAuth
{
  private static array $config = [];

  public static function init(array $config): void
  {
    self::$config = $config;
  }

  // ==================== GENERATE TOKEN ====================
  public static function generateToken(int $userId, string $role = 'user'): string
  {
    $secret = self::$config['JWT_SECRET'];
    $expiresIn = (int) (self::$config['JWT_EXPIRES_IN'] ?? 86400);

    $now = time();

    $payload = [
      'sub' => $userId,
      'role' => $role,
      'iat' => $now,
      'exp' => $now + $expiresIn,
      'iss' => 'ecommerce-api',
      'aud' => 'ecommerce-frontend'
    ];

    return JWT::encode($payload, $secret, 'HS256');
  }

  // ==================== GET TOKEN ====================
  private static function getToken(): ?string
  {
    $headers = getallheaders();

    $authHeader = $headers['Authorization']
      ?? $headers['authorization']
      ?? $_SERVER['HTTP_AUTHORIZATION']
      ?? '';

    if (!$authHeader || !str_starts_with($authHeader, 'Bearer ')) {
      return null;
    }

    return substr($authHeader, 7);
  }

  // ==================== DECODE ====================
  private static function decode(string $token): ?object
  {
    try {
      $secret = self::$config['JWT_SECRET'];
      return JWT::decode($token, new Key($secret, 'HS256'));
    } catch (\Exception $e) {
      return null;
    }
  }

  // ==================== REQUIRE AUTH ====================
  public static function requireAuth(): array
  {
    $token = self::getToken();

    if (!$token) {
      http_response_code(401);
      Response::json(false, 'Unauthorized. Token missing');
      exit;
    }

    $decoded = self::decode($token);

    if (!$decoded) {
      http_response_code(401);
      Response::json(false, 'Unauthorized. Invalid token');
      exit;
    }

    return (array) $decoded;
  }

  // ==================== REQUIRE ADMIN ====================
  public static function requireAdmin(): array
  {
    $user = self::requireAuth();

    if (($user['role'] ?? '') !== 'admin') {
      http_response_code(403);
      Response::json(false, 'Forbidden. Admin access required');
      exit;
    }

    return $user;
  }
}