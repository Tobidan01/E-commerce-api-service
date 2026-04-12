<?php

namespace App\Helpers;

use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\SignatureInvalidException;

class JwtAuth
{
  private static array $config = [];

  // Called once in index.php
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

  // ==================== READ TOKEN ====================
  public static function getAuthenticatedUser(): ?array
  {
    $headers = getallheaders();
    $authHeader = $headers['Authorization'] ?? '';

    if (!preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
      return null;
    }

    $token = $matches[1];
    $secret = self::$config['JWT_SECRET'];

    try {
      $decoded = JWT::decode($token, new Key($secret, 'HS256'));
      return (array) $decoded;

    } catch (ExpiredException $e) {
      return null;

    } catch (SignatureInvalidException $e) {
      return null;

    } catch (\Exception $e) {
      return null;
    }
  }

  // ==================== PROTECT ROUTES ====================
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

  public static function requireAdmin(): array
  {
    $token = self::getToken();

    if (!$token) {
      http_response_code(401);
      Response::json(false, 'Unauthorized. Admin token missing');
      exit;
    }

    $decoded = self::decode($token);

    if (!$decoded) {
      http_response_code(401);
      Response::json(false, 'Unauthorized. Invalid admin token');
      exit;
    }

    if (!isset($decoded->is_admin) || !$decoded->is_admin) {
      http_response_code(403);
      Response::json(false, 'Forbidden. Admin access required');
      exit;
    }

    return (array) $decoded;
  }

  private static function getToken(): ?string
  {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (empty($authHeader) || strpos($authHeader, 'Bearer ') === false) {
      return null;
    }

    return str_replace('Bearer ', '', $authHeader);
  }

  /**
   * Decode JWT token
   */
  private static function decode(string $token): ?object
  {
    try {
      $secret = $_ENV['JWT_SECRET'] ?? 'your-secret-key';
      return JWT::decode($token, new Key($secret, 'HS256'));
    } catch (\Exception $e) {
      return null;
    }
  }
}
