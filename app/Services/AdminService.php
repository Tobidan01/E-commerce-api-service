<?php

namespace App\Services;

use App\Config\Database;
use App\Models\AdminModel;
use App\Models\DashboardModel;
use App\Helpers\JwtAuth;
use App\Helpers\Validator;
use App\Helpers\Password;

class AdminService
{
  private AdminModel $adminModel;
  private DashboardModel $dashboardModel;
  private array $config;

  public function __construct(array $config)
  {
    $this->config = $config;
    $db = (new Database($config))->connect();
    $this->adminModel = new AdminModel($db);
    $this->dashboardModel = new DashboardModel($db);
  }

  /*--------------------------------------------------------------
   | ADMIN LOGIN
   --------------------------------------------------------------*/
  public function login(array $data): array
  {
    $errors = Validator::required($data, ['email', 'password']);
    if (!empty($errors)) {
      return $this->fail('Validation failed', 400, $errors);
    }

    $emailError = Validator::email($data['email']);
    if ($emailError) {
      return $this->fail($emailError, 400);
    }

    $email = strtolower(trim($data['email']));
    $admin = $this->adminModel->findByEmail($email);

    if (!$admin || !Password::verify($data['password'], $admin['password'])) {
      $this->adminModel->logAction(0, 'failed_login_attempt', 'Admin', null, ['email' => $email]);
      return $this->fail('Invalid email or password', 401);
    }

    // Check if admin account is active
    if (!$admin['is_active']) {
      return $this->fail('Admin account has been disabled', 403);
    }

    // Check if account is banned
    if ($admin['status'] === 'banned') {
      return $this->fail('Your account has been suspended', 403);
    }

    // Update last login
    $this->adminModel->updateLastLogin($admin['id']);

    // Log successful login
    $this->adminModel->logAction($admin['id'], 'admin_login', 'Admin', $admin['id']);

    unset($admin['password']);

    $token = JwtAuth::generateToken($admin['id'], 'admin');

    return $this->success('Admin login successful', 200, [
      'token' => $token,
      'user' => $admin
    ]);
  }

  /*--------------------------------------------------------------
   | GET DASHBOARD STATS
   --------------------------------------------------------------*/
  public function getDashboardStats(int $adminId): array
  {
    try {
      $stats = [
        'total_users' => $this->dashboardModel->getTotalUsers(),
        'total_orders' => $this->dashboardModel->getTotalOrders(),
        'total_revenue' => $this->dashboardModel->getTotalRevenue(),
        'total_products' => $this->dashboardModel->getTotalProducts(),
        'pending_orders' => $this->dashboardModel->getPendingOrdersCount(),
        'recent_orders' => $this->dashboardModel->getRecentOrders(10),
        'top_products' => $this->dashboardModel->getTopProducts(5),
        'sales_by_month' => $this->dashboardModel->getSalesByMonth(),
        'user_growth' => $this->dashboardModel->getUserGrowth(),
        'low_stock_products' => $this->dashboardModel->getLowStockProducts()
      ];

      $this->adminModel->logAction($adminId, 'view_dashboard');

      return $this->success('Dashboard stats retrieved', 200, $stats);
    } catch (\Exception $e) {
      return $this->fail('Error retrieving dashboard stats', 500);
    }
  }

  /*--------------------------------------------------------------
   | ORDERS MANAGEMENT
   --------------------------------------------------------------*/
  public function getAllOrders(int $adminId, int $page = 1, int $limit = 20): array
  {
    try {
      $offset = ($page - 1) * $limit;

      $db = (new Database($this->config))->connect();
      $stmt = $db->prepare("
        SELECT
          o.id,
          o.user_id,
          u.first_name,
          u.last_name,
          u.email,
          o.total,
          o.status,
          o.payment_status,
          o.payment_method,
          o.created_at
        FROM orders o
        JOIN users u ON o.user_id = u.id
        ORDER BY o.created_at DESC
        LIMIT ? OFFSET ?
      ");
      $stmt->execute([$limit, $offset]);
      $orders = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // Get total count
      $countStmt = $db->query("SELECT COUNT(*) as count FROM orders");
      $countResult = $countStmt->fetch(\PDO::FETCH_ASSOC);
      $total = (int) $countResult['count'];

      $this->adminModel->logAction($adminId, 'view_orders');

      return $this->success('Orders retrieved', 200, [
        'orders' => $orders,
        'pagination' => [
          'page' => $page,
          'limit' => $limit,
          'total' => $total,
          'pages' => ceil($total / $limit)
        ]
      ]);
    } catch (\Exception $e) {
      return $this->fail('Error retrieving orders', 500);
    }
  }

  public function getOrderDetails(int $adminId, int $orderId): array
  {
    try {
      $db = (new Database($this->config))->connect();

      $stmt = $db->prepare("
        SELECT
          o.id,
          o.user_id,
          u.first_name,
          u.last_name,
          u.email,
          u.phone,
          o.total,
          o.subtotal,
          o.shipping,
          o.status,
          o.payment_status,
          o.payment_method,
          o.address,
          o.city,
          o.state,
          o.country,
          o.notes,
          o.created_at
        FROM orders o
        JOIN users u ON o.user_id = u.id
        WHERE o.id = ?
      ");
      $stmt->execute([$orderId]);
      $order = $stmt->fetch(\PDO::FETCH_ASSOC);

      if (!$order) {
        return $this->fail('Order not found', 404);
      }

      // Get order items
      $itemsStmt = $db->prepare("
        SELECT id, product_id, name, quantity, price
        FROM order_items
        WHERE order_id = ?
      ");
      $itemsStmt->execute([$orderId]);
      $order['items'] = $itemsStmt->fetchAll(\PDO::FETCH_ASSOC);

      // Get payment info
      $paymentStmt = $db->prepare("
        SELECT id, amount, method, status, paid_at, reference
        FROM payments
        WHERE order_id = ?
      ");
      $paymentStmt->execute([$orderId]);
      $order['payment'] = $paymentStmt->fetch(\PDO::FETCH_ASSOC);

      // Get order history
      $historyStmt = $db->prepare("
        SELECT status, created_at
        FROM order_history
        WHERE order_id = ?
        ORDER BY created_at DESC
      ");
      $historyStmt->execute([$orderId]);
      $order['history'] = $historyStmt->fetchAll(\PDO::FETCH_ASSOC);

      $this->adminModel->logAction($adminId, 'view_order_details', 'Order', $orderId);

      return $this->success('Order retrieved', 200, $order);
    } catch (\Exception $e) {
      return $this->fail('Error retrieving order', 500);
    }
  }

  public function updateOrderStatus(int $adminId, int $orderId, string $status): array
  {
    try {
      $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

      if (!in_array($status, $allowedStatuses)) {
        return $this->fail('Invalid status', 400);
      }

      $db = (new Database($this->config))->connect();

      // Get current order
      $stmt = $db->prepare("SELECT status FROM orders WHERE id = ?");
      $stmt->execute([$orderId]);
      $order = $stmt->fetch(\PDO::FETCH_ASSOC);

      if (!$order) {
        return $this->fail('Order not found', 404);
      }

      $oldStatus = $order['status'];

      // Update order
      $updateStmt = $db->prepare("
        UPDATE orders
        SET status = ?, updated_at = NOW()
        WHERE id = ?
      ");
      $updateStmt->execute([$status, $orderId]);

      // Add to order history
      $historyStmt = $db->prepare("
        INSERT INTO order_history (order_id, status, created_at)
        VALUES (?, ?, NOW())
      ");
      $historyStmt->execute([$orderId, $status]);

      // Log the action
      $this->adminModel->logAction($adminId, 'update_order_status', 'Order', $orderId, [
        'old_status' => $oldStatus,
        'new_status' => $status
      ]);

      return $this->success('Order status updated', 200, ['status' => $status]);
    } catch (\Exception $e) {
      return $this->fail('Error updating order status', 500);
    }
  }

  /*--------------------------------------------------------------
   | USERS MANAGEMENT
   --------------------------------------------------------------*/
  public function getAllUsers(int $adminId, int $page = 1, int $limit = 20): array
  {
    try {
      $offset = ($page - 1) * $limit;

      $db = (new Database($this->config))->connect();

      $stmt = $db->prepare("
        SELECT
          id,
          first_name,
          last_name,
          email,
          phone,
          status,
          created_at
        FROM users
        WHERE role = 'user'
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
      ");
      $stmt->execute([$limit, $offset]);
      $users = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // Get total count
      $countStmt = $db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
      $countResult = $countStmt->fetch(\PDO::FETCH_ASSOC);
      $total = (int) $countResult['count'];

      $this->adminModel->logAction($adminId, 'view_users');

      return $this->success('Users retrieved', 200, [
        'users' => $users,
        'pagination' => [
          'page' => $page,
          'limit' => $limit,
          'total' => $total,
          'pages' => ceil($total / $limit)
        ]
      ]);
    } catch (\Exception $e) {
      return $this->fail('Error retrieving users', 500);
    }
  }

  public function banUser(int $adminId, int $userId): array
  {
    try {
      $db = (new Database($this->config))->connect();
      $stmt = $db->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
      $stmt->execute([$userId]);

      $this->adminModel->logAction($adminId, 'ban_user', 'User', $userId);

      return $this->success('User banned successfully', 200);
    } catch (\Exception $e) {
      return $this->fail('Error banning user', 500);
    }
  }

  public function unbanUser(int $adminId, int $userId): array
  {
    try {
      $db = (new Database($this->config))->connect();
      $stmt = $db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
      $stmt->execute([$userId]);

      $this->adminModel->logAction($adminId, 'unban_user', 'User', $userId);

      return $this->success('User unbanned successfully', 200);
    } catch (\Exception $e) {
      return $this->fail('Error unbanning user', 500);
    }
  }

  /*--------------------------------------------------------------
   | PRODUCTS MANAGEMENT
   --------------------------------------------------------------*/
  public function getAllProducts(int $adminId, int $page = 1, int $limit = 20): array
  {
    try {
      $offset = ($page - 1) * $limit;

      $db = (new Database($this->config))->connect();

      $stmt = $db->prepare("
        SELECT
          id,
          name,
          price,
          stock,
          description,
          created_at
        FROM products
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
      ");
      $stmt->execute([$limit, $offset]);
      $products = $stmt->fetchAll(\PDO::FETCH_ASSOC);

      // Get total count
      $countStmt = $db->query("SELECT COUNT(*) as count FROM products");
      $countResult = $countStmt->fetch(\PDO::FETCH_ASSOC);
      $total = (int) $countResult['count'];

      $this->adminModel->logAction($adminId, 'view_products');

      return $this->success('Products retrieved', 200, [
        'products' => $products,
        'pagination' => [
          'page' => $page,
          'limit' => $limit,
          'total' => $total,
          'pages' => ceil($total / $limit)
        ]
      ]);
    } catch (\Exception $e) {
      return $this->fail('Error retrieving products', 500);
    }
  }

  /*--------------------------------------------------------------
   | ACTIVITY LOGS
   --------------------------------------------------------------*/
  public function getActivityLogs(int $adminId, int $page = 1, int $limit = 50): array
  {
    try {
      $offset = ($page - 1) * $limit;

      $logs = $this->adminModel->getActivityLogs($limit, $offset);
      $total = $this->adminModel->countActivityLogs();

      return $this->success('Activity logs retrieved', 200, [
        'logs' => $logs,
        'pagination' => [
          'page' => $page,
          'limit' => $limit,
          'total' => $total,
          'pages' => ceil($total / $limit)
        ]
      ]);
    } catch (\Exception $e) {
      return $this->fail('Error retrieving activity logs', 500);
    }
  }

  /*--------------------------------------------------------------
   | HELPERS
   --------------------------------------------------------------*/
  private function success(string $message, int $code, ?array $data = null): array
  {
    return ['success' => true, 'message' => $message, 'code' => $code, 'data' => $data];
  }

  private function fail(string $message, int $code, array $errors = []): array
  {
    return ['success' => false, 'message' => $message, 'code' => $code, 'data' => !empty($errors) ? $errors : null];
  }
}