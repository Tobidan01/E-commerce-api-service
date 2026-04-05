<?php

namespace App\Services;

use App\Config\Database;
use App\Models\UsersModel;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use App\Helpers\Validator;

class AuthService
{
  private UsersModel $userModel;
  private array $config;

  public function __construct(array $config)
  {
    $this->config = $config;

    // Pass config into Database
    $db = (new Database($config))->connect();
    $this->userModel = new UsersModel($db);
  }
  public function register(array $data): array
  {
    error_log("🔥 REGISTER HIT");

    $errors = Validator::required($data, ['first_name', 'last_name', 'email', 'password']);
    if (!empty($errors)) {
      return $this->fail("Validation failed", 400, $errors);
    }

    $emailError = Validator::email($data['email']);
    if ($emailError) {
      return $this->fail($emailError, 400);
    }

    $passError = Validator::minLength($data, 'password', 8);
    if ($passError) {
      return $this->fail($passError, 400);
    }

    $email = strtolower(trim($data['email']));

    // 🔥 LOG DB CONFIG
    error_log("DB HOST: " . ($this->config['DB_HOST'] ?? 'NULL'));
    error_log("DB NAME: " . ($this->config['DB_NAME'] ?? 'NULL'));

    if ($this->userModel->findByEmail($email)) {
      return $this->fail("This email is already registered", 409);
    }

    $userId = $this->userModel->create([
      'first_name' => trim($data['first_name']),
      'last_name' => trim($data['last_name']),
      'email' => $email,
      'password' => password_hash($data['password'], PASSWORD_BCRYPT),
      'phone' => $data['phone'] ?? null
    ]);

    // 🔥 LOG INSERT RESULT
    error_log("INSERTED USER ID: " . json_encode($userId));

    $user = $this->userModel->findById($userId);

    // 🔥 LOG FETCH RESULT
    error_log("FETCHED USER: " . json_encode($user));

    unset($user['password']);

    return $this->success("Registration successful", 201, $user);
  }

  public function login(array $data): array
  {
    // 🔥 Normalize input FIRST
    $data['email'] = strtolower(trim($data['email'] ?? ''));
    $data['password'] = trim($data['password'] ?? '');

    $errors = Validator::required($data, ['email', 'password']);
    if (!empty($errors)) {
      return $this->fail("Validation failed", 400, $errors);
    }

    $emailError = Validator::email($data['email']);
    if ($emailError) {
      return $this->fail($emailError, 400);
    }

    $user = $this->userModel->findByEmail($data['email']);

    if (!$user || !password_verify($data['password'], $user['password'])) {
      return $this->fail("Invalid email or password", 401);
    }

    if ($user['status'] === 'banned') {
      return $this->fail("Your account has been suspended", 403);
    }

    unset($user['password']);

    $token = $this->generateJwt($user);

    return $this->success("Login successful", 200, [
      'token' => $token,
      'user' => $user
    ]);
  }

  public function verifyToken(string $token): ?array
  {
    $secret = $this->config['JWT_SECRET'];

    try {
      $decoded = JWT::decode($token, new Key($secret, 'HS256'));
      return (array) $decoded;
    } catch (\Exception $e) {
      return null;
    }
  }

  private function generateJwt(array $user): string
  {
    $secret = $this->config['JWT_SECRET'];
    $expiresIn = (int) $this->config['JWT_EXPIRES_IN'];
    $now = time();

    $payload = [
      'sub' => $user['id'],
      'email' => $user['email'],
      'role' => $user['role'] ?? 'user',
      'iat' => $now,
      'exp' => $now + $expiresIn,
      'iss' => 'ecommerce-api',
      'aud' => 'ecommerce-frontend'
    ];

    return JWT::encode($payload, $secret, 'HS256');
  }

  private function success(string $message, int $code, ?array $data = null): array
  {
    return ['success' => true, 'message' => $message, 'code' => $code, 'data' => $data];
  }

  private function fail(string $message, int $code, array $errors = []): array
  {
    return ['success' => false, 'message' => $message, 'code' => $code, 'data' => !empty($errors) ? $errors : null];
  }
}
