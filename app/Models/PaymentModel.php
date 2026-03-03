<?php

namespace App\Models;

use PDO;

class PaymentModel
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  // For cash on delivery
  public function create(array $data): int
  {
    $stmt = $this->db->prepare("
            INSERT INTO payments (order_id, user_id, amount, method, status, created_at)
            VALUES (:order_id, :user_id, :amount, :method, :status, NOW())
        ");
    $stmt->execute([
      ':order_id' => $data['order_id'],
      ':user_id' => $data['user_id'],
      ':amount' => $data['amount'],
      ':method' => $data['method'],
      ':status' => $data['status']
    ]);
    return (int) $this->db->lastInsertId();
  }

  // For card/transfer — before order is created
  public function createPending(array $data): void
  {
    $stmt = $this->db->prepare("
            INSERT INTO payments
                (user_id, amount, method, status, reference,
                 address, city, state, country, notes, created_at)
            VALUES
                (:user_id, :amount, :method, 'pending', :reference,
                 :address, :city, :state, :country, :notes, NOW())
        ");
    $stmt->execute([
      ':user_id' => $data['user_id'],
      ':amount' => $data['amount'],
      ':method' => $data['method'],
      ':reference' => $data['reference'],
      ':address' => $data['address'],
      ':city' => $data['city'],
      ':state' => $data['state'],
      ':country' => $data['country'],
      ':notes' => $data['notes'] ?? null
    ]);
  }

  public function getPendingByReference(string $reference): ?array
  {
    $stmt = $this->db->prepare("
            SELECT * FROM payments WHERE reference = ? LIMIT 1
        ");
    $stmt->execute([$reference]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public function markPaid(string $reference, int $orderId, array $gatewayResponse): void
  {
    $stmt = $this->db->prepare("
            UPDATE payments
            SET status           = 'success',
                order_id         = :order_id,
                gateway_response = :response,
                paid_at          = NOW()
            WHERE reference = :reference
        ");
    $stmt->execute([
      ':order_id' => $orderId,
      ':response' => json_encode($gatewayResponse),
      ':reference' => $reference
    ]);
  }
}