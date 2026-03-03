<?php

namespace App\Models;

use PDO;

class CartModel
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  public function getCart(int $userId): array
  {
    $stmt = $this->db->prepare("
            SELECT 
                c.id, c.product_id, c.quantity,
                p.name, p.price, p.compare_price,
                p.slug, p.stock,
                (SELECT image_url FROM product_images 
                 WHERE product_id = p.id AND is_primary = 1 
                 LIMIT 1) AS image
            FROM cart c
            JOIN products p ON p.id = c.product_id
            WHERE c.user_id = ?
            ORDER BY c.id DESC
        ");
    $stmt->execute([$userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function getItem(int $id): ?array
  {
    $stmt = $this->db->prepare("SELECT * FROM cart WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public function getCartItem(int $userId, int $productId): ?array
  {
    $stmt = $this->db->prepare("
            SELECT * FROM cart 
            WHERE user_id = ? AND product_id = ?
            LIMIT 1
        ");
    $stmt->execute([$userId, $productId]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public function addToCart(int $userId, int $productId, int $qty): int
  {
    $existing = $this->getCartItem($userId, $productId);

    if ($existing) {
      $stmt = $this->db->prepare("
                UPDATE cart 
                SET quantity = quantity + :qty, updated_at = NOW()
                WHERE id = :id
            ");
      $stmt->execute(['qty' => $qty, 'id' => $existing['id']]);
      return $existing['id'];
    }

    $stmt = $this->db->prepare("
            INSERT INTO cart (user_id, product_id, quantity)
            VALUES (?, ?, ?)
        ");
    $stmt->execute([$userId, $productId, $qty]);
    return (int) $this->db->lastInsertId();
  }

  public function updateItem(int $id, int $qty): void
  {
    $stmt = $this->db->prepare("
            UPDATE cart SET quantity = ?, updated_at = NOW() WHERE id = ?
        ");
    $stmt->execute([$qty, $id]);
  }

  public function removeItem(int $id): void
  {
    $stmt = $this->db->prepare("DELETE FROM cart WHERE id = ?");
    $stmt->execute([$id]);
  }

  public function clearCart(int $userId): void
  {
    $stmt = $this->db->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->execute([$userId]);
  }
}