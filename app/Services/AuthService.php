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

    // ✅ CLEAN INPUTS
    $email = strtolower(trim($data['email']));
    $password = trim($data['password']);

    // 🔥 DEBUG
    error_log("REGISTER PASSWORD RAW: >" . $password . "<");
    error_log("REGISTER PASSWORD LENGTH: " . strlen($password));

    if ($this->userModel->findByEmail($email)) {
      return $this->fail("This email is already registered", 409);
    }

    $userId = $this->userModel->create([
      'first_name' => trim($data['first_name']),
      'last_name' => trim($data['last_name']),
      'email' => $email,
      'password' => password_hash($password, PASSWORD_BCRYPT), // ✅ HASH TRIMMED PASSWORD
      'phone' => $data['phone'] ?? null
    ]);

    error_log("INSERTED USER ID: " . json_encode($userId));

    $user = $this->userModel->findById($userId);

    error_log("FETCHED USER: " . json_encode($user));

    unset($user['password']);

    return $this->success("Registration successful", 201, $user);
  }

  public function login(array $data): array
  {
    $errors = Validator::required($data, ['email', 'password']);
    if (!empty($errors)) {
      return $this->fail("Validation failed", 400, $errors);
    }

    $emailError = Validator::email($data['email']);
    if ($emailError) {
      return $this->fail($emailError, 400);
    }

    // ✅ CLEAN INPUTS
    $email = strtolower(trim($data['email']));
    $password = trim($data['password']);

    $user = $this->userModel->findByEmail($email);

    // 🔥 DEBUG
    error_log("LOGIN EMAIL: " . $email);
    error_log("USER FROM DB: " . json_encode($user));

    if ($user) {
      error_log("DB PASSWORD HASH: " . $user['password']);
      error_log("INPUT PASSWORD RAW: >" . $password . "<");
      error_log("INPUT PASSWORD LENGTH: " . strlen($password));
      error_log("VERIFY RESULT: " . (password_verify($password, $user['password']) ? 'TRUE' : 'FALSE'));
    }

    // ✅ VERIFY TRIMMED PASSWORD
    if (!$user || !password_verify($password, $user['password'])) {
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
