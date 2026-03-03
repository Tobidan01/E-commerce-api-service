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
    $user = JwtAuth::requireAuth();
    $result = $this->service->verifyPayment((int) $user['sub'], $reference);
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