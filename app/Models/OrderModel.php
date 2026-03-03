<?php

namespace App\Models;

use PDO;

class OrderModel
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  public function createOrder(int $userId, array $data): int
  {
    $stmt = $this->db->prepare("
            INSERT INTO orders (
                user_id, subtotal, shipping, total, status,
                address, city, state, country,
                payment_method, payment_status, notes
            )
            VALUES (
                :user_id, :subtotal, :shipping, :total, 'pending',
                :address, :city, :state, :country,
                :payment_method, 'unpaid', :notes
            )
        ");

    $stmt->execute([
      ':user_id' => $userId,
      ':subtotal' => $data['subtotal'],
      ':shipping' => $data['shipping'],
      ':total' => $data['total'],
      ':address' => $data['address'],
      ':city' => $data['city'],
      ':state' => $data['state'],
      ':country' => $data['country'],
      ':payment_method' => $data['payment_method'] ?? 'cash_on_delivery',
      ':notes' => $data['notes'] ?? null
    ]);

    return (int) $this->db->lastInsertId();
  }

  public function getOrder(int $orderId): ?array
  {
    $stmt = $this->db->prepare("SELECT * FROM orders WHERE id = ? LIMIT 1");
    $stmt->execute([$orderId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public function getOrdersByUser(int $userId): array
  {
    $stmt = $this->db->prepare("
            SELECT o.*,
                COUNT(oi.id) AS item_count
            FROM orders o
            LEFT JOIN order_items oi ON oi.order_id = o.id
            WHERE o.user_id = ?
            GROUP BY o.id
            ORDER BY o.id DESC
        ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function getAllOrders(): array
  {
    $stmt = $this->db->query("
            SELECT o.*,
                u.first_name, u.last_name, u.email,
                COUNT(oi.id) AS item_count
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            LEFT JOIN order_items oi ON oi.order_id = o.id
            GROUP BY o.id
            ORDER BY o.id DESC
        ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function getOrderWithItems(int $orderId): ?array
  {
    $order = $this->getOrder($orderId);
    if (!$order)
      return null;

    $stmt = $this->db->prepare("
            SELECT oi.*, p.slug,
                (SELECT image_url FROM product_images 
                 WHERE product_id = oi.product_id AND is_primary = 1 
                 LIMIT 1) AS image
            FROM order_items oi
            LEFT JOIN products p ON p.id = oi.product_id
            WHERE oi.order_id = ?
        ");
    $stmt->execute([$orderId]);
    $order['items'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

    return $order;
  }

  public function updateStatus(int $orderId, string $status): bool
  {
    $stmt = $this->db->prepare("
            UPDATE orders 
            SET status = :status, updated_at = NOW() 
            WHERE id = :id
        ");
    return $stmt->execute([':status' => $status, ':id' => $orderId]);
  }

  public function countOrders(): int
  {
    return (int) $this->db->query("SELECT COUNT(*) FROM orders")->fetchColumn();
  }

  public function sumRevenue(): float
  {
    return (float) $this->db->query("SELECT COALESCE(SUM(total), 0) FROM orders")->fetchColumn();
  }

  public function countByStatus(string $status): int
  {
    $stmt = $this->db->prepare("SELECT COUNT(*) FROM orders WHERE status = ?");
    $stmt->execute([$status]);
    return (int) $stmt->fetchColumn();
  }

  public function getRecentOrders(): array
  {
    $stmt = $this->db->query("
            SELECT o.*, u.first_name, u.last_name, u.email
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id
            ORDER BY o.created_at DESC 
            LIMIT 10
        ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function updatePaymentStatus(int $orderId, string $status): bool
  {
    $stmt = $this->db->prepare("
        UPDATE orders 
        SET payment_status = :status, updated_at = NOW()
        WHERE id = :id
    ");
    return $stmt->execute([
      ':status' => $status,
      ':id' => $orderId
    ]);
  }
}