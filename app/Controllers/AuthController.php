<?php

namespace App\Controllers;

use App\Helpers\Response;
use App\Helpers\JwtAuth;
use App\Services\AuthService;
use App\Models\UsersModel;
use App\Config\Database;

class AuthController
{
  private AuthService $service;
  private UsersModel $userModel;
  private array $config;

  public function __construct(AuthService $service, array $config)
  {
    $this->service = $service;
    $this->config = $config;
    $db = (new Database($config))->connect();
    $this->userModel = new UsersModel($db);
  }

  public function register(): void
  {
    $this->setJsonHeader();
    $input = $this->getJsonInput();

    if ($input === null) {
      $this->badRequest("Invalid JSON payload");
      return;
    }

    $result = $this->service->register($input);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  public function login(): void
  {
    $this->setJsonHeader();
    $input = $this->getJsonInput();

    if ($input === null) {
      $this->badRequest("Invalid JSON payload");
      return;
    }

    $result = $this->service->login($input);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  public function me(): void
  {
    $this->setJsonHeader();

    $jwtPayload = JwtAuth::requireAuth();
    $userId = (int) $jwtPayload['sub'];
    $user = $this->userModel->findById($userId);

    if (!$user) {
      http_response_code(404);
      Response::json(false, "User not found");
      return;
    }

    unset($user['password']);
    http_response_code(200);
    Response::json(true, "Authenticated user retrieved", $user);
  }

  public function loginWithGoogle(): void
  {
    $this->setJsonHeader();

    try {
      $input = $this->getJsonInput();
      $idToken = trim($input['token'] ?? '');

      if (!$idToken) {
        $this->badRequest("Missing Google token");
        return;
      }

      $client = new \Google_Client([
        'client_id' => $this->config['GOOGLE_CLIENT_ID'] ?? ''
      ]);

      $payload = $client->verifyIdToken($idToken);

      if (!$payload) {
        http_response_code(401);
        Response::json(false, "Invalid Google token");
        return;
      }

      if (!$payload['email_verified']) {
        http_response_code(403);
        Response::json(false, "Google email not verified");
        return;
      }

      $email = $payload['email'];
      $googleId = $payload['sub'];
      $firstName = $payload['given_name'] ?? '';
      $lastName = $payload['family_name'] ?? '';

      $user = $this->userModel->findByGoogleId($googleId)
        ?? $this->userModel->findByEmail($email);

      if (!$user) {
        $userId = $this->userModel->createGoogleUser([
          'first_name' => $firstName,
          'last_name' => $lastName,
          'email' => $email,
          'google_id' => $googleId
        ]);
        $user = $this->userModel->findById($userId);
      }

      if (!$user) {
        http_response_code(500);
        Response::json(false, "Failed to retrieve user");
        return;
      }

      if (($user['status'] ?? '') === 'banned') {
        http_response_code(403);
        Response::json(false, "Your account has been suspended");
        return;
      }

      $token = JwtAuth::generateToken($user['id'], $user['role'] ?? 'user');

      http_response_code(200);
      Response::json(true, "Login successful", [
        'token' => $token,
        'user' => [
          'id' => $user['id'],
          'first_name' => $user['first_name'],
          'last_name' => $user['last_name'],
          'email' => $user['email'],
          'role' => $user['role'] ?? 'user',
          'provider' => $user['provider'] ?? 'google'
        ]
      ]);

    } catch (\Exception $e) {
      http_response_code(500);
      Response::json(false, "An error occurred: " . $e->getMessage());
    }
  }

  private function setJsonHeader(): void
  {
    header('Content-Type: application/json');
  }

  private function getJsonInput(): ?array
  {
    $raw = file_get_contents('php://input');
    $data = json_decode($raw, true);
    return json_last_error() === JSON_ERROR_NONE ? $data : null;
  }

  private function badRequest(string $message): void
  {
    http_response_code(400);
    Response::json(false, $message);
  }
}