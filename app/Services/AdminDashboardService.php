<?php

namespace App\Services;

use App\Config\Database;
use App\Models\DashboardModel;
use App\Models\AdminModel;
use PDO;

class AdminDashboardService
{
  private DashboardModel $dashboardModel;
  private AdminModel $adminModel;
  private PDO $db;
  private array $config;

  public function __construct(array $config)
  {
    $this->config = $config;
    $this->db = (new Database($config))->connect();
    $this->dashboardModel = new DashboardModel($this->db);
    $this->adminModel = new AdminModel($this->db);
  }

  /**
   * Get Dashboard Statistics
   */
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

  /**
   * Get All Orders with pagination
   */
  public function getAllOrders(int $adminId, int $page = 1, int $limit = 20): array
  {
    try {
      $offset = ($page - 1) * $limit;

      $stmt = $this->db->prepare("
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
      $orders = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Get total count
      $countStmt = $this->db->query("SELECT COUNT(*) as count FROM orders");
      $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
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

  /**
   * Get Order Details
   */
  public function getOrderDetails(int $adminId, int $orderId): array
  {
    try {
      $stmt = $this->db->prepare("
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
      $order = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$order) {
        return $this->fail('Order not found', 404);
      }

      // Get order items
      $itemsStmt = $this->db->prepare("
        SELECT id, product_id, name, quantity, price
        FROM order_items
        WHERE order_id = ?
      ");
      $itemsStmt->execute([$orderId]);
      $order['items'] = $itemsStmt->fetchAll(PDO::FETCH_ASSOC);

      // Get payment info
      $paymentStmt = $this->db->prepare("
        SELECT id, amount, method, status, paid_at, reference
        FROM payments
        WHERE order_id = ?
      ");
      $paymentStmt->execute([$orderId]);
      $order['payment'] = $paymentStmt->fetch(PDO::FETCH_ASSOC);

      // Get order history
      $historyStmt = $this->db->prepare("
        SELECT status, created_at
        FROM order_history
        WHERE order_id = ?
        ORDER BY created_at DESC
      ");
      $historyStmt->execute([$orderId]);
      $order['history'] = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

      $this->adminModel->logAction($adminId, 'view_order_details', 'Order', $orderId);

      return $this->success('Order retrieved', 200, $order);
    } catch (\Exception $e) {
      return $this->fail('Error retrieving order', 500);
    }
  }

  /**
   * Update Order Status
   */
  public function updateOrderStatus(int $adminId, int $orderId, string $status): array
  {
    try {
      $allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];

      if (!in_array($status, $allowedStatuses)) {
        return $this->fail('Invalid status', 400);
      }

      // Get current order
      $stmt = $this->db->prepare("SELECT status FROM orders WHERE id = ?");
      $stmt->execute([$orderId]);
      $order = $stmt->fetch(PDO::FETCH_ASSOC);

      if (!$order) {
        return $this->fail('Order not found', 404);
      }

      $oldStatus = $order['status'];

      // Update order
      $updateStmt = $this->db->prepare("
        UPDATE orders
        SET status = ?, updated_at = NOW()
        WHERE id = ?
      ");
      $updateStmt->execute([$status, $orderId]);

      // Add to order history
      $historyStmt = $this->db->prepare("
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

  /**
   * Get All Users
   */
  public function getAllUsers(int $adminId, int $page = 1, int $limit = 20): array
  {
    try {
      $offset = ($page - 1) * $limit;

      $stmt = $this->db->prepare("
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
      $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Get total count
      $countStmt = $this->db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
      $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
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

  /**
   * Ban User
   */
  public function banUser(int $adminId, int $userId): array
  {
    try {
      $stmt = $this->db->prepare("UPDATE users SET status = 'banned' WHERE id = ?");
      $stmt->execute([$userId]);

      $this->adminModel->logAction($adminId, 'ban_user', 'User', $userId);

      return $this->success('User banned successfully', 200);
    } catch (\Exception $e) {
      return $this->fail('Error banning user', 500);
    }
  }

  /**
   * Unban User
   */
  public function unbanUser(int $adminId, int $userId): array
  {
    try {
      $stmt = $this->db->prepare("UPDATE users SET status = 'active' WHERE id = ?");
      $stmt->execute([$userId]);

      $this->adminModel->logAction($adminId, 'unban_user', 'User', $userId);

      return $this->success('User unbanned successfully', 200);
    } catch (\Exception $e) {
      return $this->fail('Error unbanning user', 500);
    }
  }

  /**
   * Get All Products
   */
  public function getAllProducts(int $adminId, int $page = 1, int $limit = 20): array
  {
    try {
      $offset = ($page - 1) * $limit;

      $stmt = $this->db->prepare("
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
      $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

      // Get total count
      $countStmt = $this->db->query("SELECT COUNT(*) as count FROM products");
      $countResult = $countStmt->fetch(PDO::FETCH_ASSOC);
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

  /**
   * Get Activity Logs
   */
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

  private function success(string $message, int $code, ?array $data = null): array
  {
    return ['success' => true, 'message' => $message, 'code' => $code, 'data' => $data];
  }

  private function fail(string $message, int $code, array $errors = []): array
  {
    return ['success' => false, 'message' => $message, 'code' => $code, 'data' => !empty($errors) ? $errors : null];
  }
}