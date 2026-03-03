<?php

namespace App\Controllers;

use App\Services\OrderService;
use App\Helpers\Response;
use App\Helpers\JwtAuth;

class OrderController
{
  private OrderService $service;

  public function __construct(OrderService $service)
  {
    $this->service = $service;
  }

  // POST /api/orders
  public function createOrder(): void
  {
    $user = JwtAuth::requireAuth();
    $input = json_decode(file_get_contents("php://input"), true);

    $result = $this->service->createOrder((int) $user['sub'], $input ?? []);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  // GET /api/orders
  public function getMyOrders(): void
  {
    $user = JwtAuth::requireAuth();
    $orders = $this->service->getMyOrders((int) $user['sub']);
    http_response_code(200);
    Response::json(true, "Orders retrieved", $orders);
  }

  // GET /api/orders/{id}
  public function getOrderById(int $id): void
  {
    $user = JwtAuth::requireAuth();
    $order = $this->service->getOrderById((int) $user['sub'], $id);

    if (!$order) {
      http_response_code(404);
      Response::json(false, "Order not found");
      return;
    }

    http_response_code(200);
    Response::json(true, "Order retrieved", $order);
  }

  // DELETE /api/orders/{id}
  public function cancelOrder(int $id): void
  {
    $user = JwtAuth::requireAuth();
    $result = $this->service->cancelOrder((int) $user['sub'], $id);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message']);
  }

  // ADMIN: GET /api/admin/orders
  public function getAllOrders(): void
  {
    JwtAuth::requireAdmin();
    $orders = $this->service->getAllOrders();
    http_response_code(200);
    Response::json(true, "All orders retrieved", $orders);
  }

  // ADMIN: PUT /api/admin/orders/{id}/status
  public function updateStatus(int $id): void
  {
    JwtAuth::requireAdmin();
    $input = json_decode(file_get_contents("php://input"), true);
    $result = $this->service->updateStatus($id, $input['status'] ?? null);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message']);
  }

  // ADMIN: GET /api/admin/orders/{id}/timeline
  public function getOrderTimeline(int $id): void
  {
    JwtAuth::requireAdmin();
    $timeline = $this->service->getOrderTimeline($id);
    http_response_code(200);
    Response::json(true, "Order timeline retrieved", $timeline);
  }

  // ADMIN: GET /api/admin/dashboard
  public function dashboard(): void
  {
    JwtAuth::requireAdmin();
    $stats = $this->service->getDashboardStats();
    http_response_code(200);
    Response::json(true, "Dashboard stats", $stats);
  }
}