<?php

namespace App\Models;

use PDO;

class DashboardModel
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  /**
   * Get total users count
   */
  public function getTotalUsers(): int
  {
    $stmt = $this->db->query("SELECT COUNT(*) as count FROM users WHERE role = 'user'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int) $result['count'];
  }

  /**
   * Get total orders count
   */
  public function getTotalOrders(): int
  {
    $stmt = $this->db->query("SELECT COUNT(*) as count FROM orders");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int) $result['count'];
  }

  /**
   * Get total revenue
   */
  public function getTotalRevenue(): float
  {
    $stmt = $this->db->query("
      SELECT COALESCE(SUM(total), 0) as revenue
      FROM orders
      WHERE status = 'completed' OR payment_status = 'paid'
    ");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (float) $result['revenue'];
  }

  /**
   * Get total products count
   */
  public function getTotalProducts(): int
  {
    $stmt = $this->db->query("SELECT COUNT(*) as count FROM products");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int) $result['count'];
  }

  /**
   * Get recent orders
   */
  public function getRecentOrders(int $limit = 10): array
  {
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
        o.created_at
      FROM orders o
      JOIN users u ON o.user_id = u.id
      ORDER BY o.created_at DESC
      LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Get top products by sales
   */
  public function getTopProducts(int $limit = 5): array
  {
    $stmt = $this->db->prepare("
      SELECT
        p.id,
        p.name,
        p.price,
        COUNT(oi.id) as order_count,
        SUM(oi.quantity) as total_sold,
        SUM(oi.quantity * oi.price) as total_revenue
      FROM products p
      LEFT JOIN order_items oi ON p.id = oi.product_id
      GROUP BY p.id, p.name, p.price
      ORDER BY total_sold DESC
      LIMIT ?
    ");
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Get sales by month
   */
  public function getSalesByMonth(): array
  {
    $stmt = $this->db->query("
      SELECT
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as order_count,
        COALESCE(SUM(total), 0) as total_revenue
      FROM orders
      WHERE status = 'completed' OR payment_status = 'paid'
      GROUP BY DATE_FORMAT(created_at, '%Y-%m')
      ORDER BY month DESC
      LIMIT 12
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Get user growth by month
   */
  public function getUserGrowth(): array
  {
    $stmt = $this->db->query("
      SELECT
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as new_users
      FROM users
      WHERE role = 'user'
      GROUP BY DATE_FORMAT(created_at, '%Y-%m')
      ORDER BY month DESC
      LIMIT 12
    ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /**
   * Get pending orders count
   */
  public function getPendingOrdersCount(): int
  {
    $stmt = $this->db->query("SELECT COUNT(*) as count FROM orders WHERE status = 'pending'");
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return (int) $result['count'];
  }

  /**
   * Get low stock products
   */
  public function getLowStockProducts(int $threshold = 5): array
  {
    $stmt = $this->db->prepare("
      SELECT id, name, stock, price
      FROM products
      WHERE stock <= ?
      ORDER BY stock ASC
      LIMIT 10
    ");
    $stmt->execute([$threshold]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}