<?php

namespace App\Models;

use PDO;

class OrderItemModel
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  public function addItem(int $orderId, int $productId, string $name, int $qty, float $price): void
  {
    $stmt = $this->db->prepare("
            INSERT INTO order_items (order_id, product_id, name, quantity, price, subtotal)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
    $stmt->execute([
      $orderId,
      $productId,
      $name,
      $qty,
      $price,
      round($price * $qty, 2)
    ]);
  }
}