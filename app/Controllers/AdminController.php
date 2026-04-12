<?php

namespace App\Controllers;

use App\Services\AdminService;
use App\Helpers\Response;
use App\Helpers\JwtAuth;

class AdminController
{
  private AdminService $service;

  public function __construct(AdminService $service)
  {
    $this->service = $service;
  }

  /*--------------------------------------------------------------
   | POST /api/admin/login
   | Login admin user
   --------------------------------------------------------------*/
  public function login(): void
  {
    $input = json_decode(file_get_contents("php://input"), true);

    $result = $this->service->login($input ?? []);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  /*--------------------------------------------------------------
   | GET /api/admin/dashboard
   | Get dashboard statistics (Protected - Admin only)
   --------------------------------------------------------------*/
  public function getDashboard(): void
  {
    $admin = JwtAuth::requireAdmin();

    $result = $this->service->getDashboardStats((int) $admin['sub']);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  /*--------------------------------------------------------------
   | GET /api/admin/orders
   | Get all orders with pagination (Protected - Admin only)
   | Query params: page, limit
   --------------------------------------------------------------*/
  public function getOrders(): void
  {
    $admin = JwtAuth::requireAdmin();

    $page = (int) ($_GET['page'] ?? 1);
    $limit = (int) ($_GET['limit'] ?? 20);

    $result = $this->service->getAllOrders((int) $admin['sub'], $page, $limit);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  /*--------------------------------------------------------------
   | GET /api/admin/orders/:id
   | Get specific order details (Protected - Admin only)
   --------------------------------------------------------------*/
  public function getOrderDetails(int $orderId): void
  {
    $admin = JwtAuth::requireAdmin();

    $result = $this->service->getOrderDetails((int) $admin['sub'], $orderId);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  /*--------------------------------------------------------------
   | PUT /api/admin/orders/:id/status
   | Update order status (Protected - Admin only)
   | Body: { "status": "shipped" }
   --------------------------------------------------------------*/
  public function updateOrderStatus(int $orderId): void
  {
    $admin = JwtAuth::requireAdmin();
    $input = json_decode(file_get_contents("php://input"), true);

    if (empty($input['status'])) {
      http_response_code(400);
      Response::json(false, 'Status is required');
      return;
    }

    $result = $this->service->updateOrderStatus(
      (int) $admin['sub'],
      $orderId,
      $input['status']
    );
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  /*--------------------------------------------------------------
   | GET /api/admin/users
   | Get all users with pagination (Protected - Admin only)
   | Query params: page, limit
   --------------------------------------------------------------*/
  public function getUsers(): void
  {
    $admin = JwtAuth::requireAdmin();

    $page = (int) ($_GET['page'] ?? 1);
    $limit = (int) ($_GET['limit'] ?? 20);

    $result = $this->service->getAllUsers((int) $admin['sub'], $page, $limit);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  /*--------------------------------------------------------------
   | POST /api/admin/users/:id/ban
   | Ban a user (Protected - Admin only)
   --------------------------------------------------------------*/
  public function banUser(int $userId): void
  {
    $admin = JwtAuth::requireAdmin();

    $result = $this->service->banUser((int) $admin['sub'], $userId);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  /*--------------------------------------------------------------
   | POST /api/admin/users/:id/unban
   | Unban a user (Protected - Admin only)
   --------------------------------------------------------------*/
  public function unbanUser(int $userId): void
  {
    $admin = JwtAuth::requireAdmin();

    $result = $this->service->unbanUser((int) $admin['sub'], $userId);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  /*--------------------------------------------------------------
   | GET /api/admin/products
   | Get all products with pagination (Protected - Admin only)
   | Query params: page, limit
   --------------------------------------------------------------*/
  public function getProducts(): void
  {
    $admin = JwtAuth::requireAdmin();

    $page = (int) ($_GET['page'] ?? 1);
    $limit = (int) ($_GET['limit'] ?? 20);

    $result = $this->service->getAllProducts((int) $admin['sub'], $page, $limit);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  /*--------------------------------------------------------------
   | GET /api/admin/logs
   | Get admin activity logs (Protected - Admin only)
   | Query params: page, limit
   --------------------------------------------------------------*/
  public function getActivityLogs(): void
  {
    $admin = JwtAuth::requireAdmin();

    $page = (int) ($_GET['page'] ?? 1);
    $limit = (int) ($_GET['limit'] ?? 50);

    $result = $this->service->getActivityLogs((int) $admin['sub'], $page, $limit);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }
}