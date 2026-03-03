<?php

namespace App\Models;

use PDO;

class OrderHistoryModel
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  public function addStatus(int $orderId, string $status): bool
  {
    $stmt = $this->db->prepare("
            INSERT INTO order_status_history (order_id, status, created_at)
            VALUES (:order_id, :status, NOW())
        ");
    return $stmt->execute([
      ':order_id' => $orderId,
      ':status' => $status
    ]);
  }

  public function getHistory(int $orderId): array
  {
    $stmt = $this->db->prepare("
            SELECT * FROM order_status_history
            WHERE order_id = ?
            ORDER BY created_at ASC
        ");
    $stmt->execute([$orderId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}