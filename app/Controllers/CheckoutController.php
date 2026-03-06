<?php

namespace App\Controllers;

use App\Services\PaymentService;
use App\Helpers\Response;
use App\Helpers\JwtAuth;

class CheckoutController
{
  private PaymentService $service;

  public function __construct(PaymentService $service)
  {
    $this->service = $service;
  }

  // POST /api/checkout/cash
  public function cash(): void
  {
    $user = JwtAuth::requireAuth();
    $input = json_decode(file_get_contents("php://input"), true);

    $result = $this->service->cashOnDelivery((int) $user['sub'], $input ?? []);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  // POST /api/checkout/initiate
  public function initiate(): void
  {
    $user = JwtAuth::requireAuth();
    $input = json_decode(file_get_contents("php://input"), true);

    $result = $this->service->initiatePayment((int) $user['sub'], $input ?? []);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  // GET /api/checkout/verify/{reference}
  public function verify(string $reference): void
  {
    // Get user from token if available, otherwise get from payment record
    $userId = null;

    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($authHeader) {
      try {
        $user = JwtAuth::requireAuth();
        $userId = (int) $user['sub'];
      } catch (\Exception $e) {
        // No token — will get userId from payment record
      }
    }

    $result = $this->service->verifyPayment($userId, $reference);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }
  // POST /api/checkout/webhook
  public function webhook(): void
  {
    $result = $this->service->handleWebhook();
    http_response_code($result['code']);
    Response::json($result['success'], $result['message']);
  }
}