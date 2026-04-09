<?php

namespace App\Models;

use PDO;
use PDOException;

class WishlistModel
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  public function add(int $userId, int $productId): bool
  {
    $stmt = $this->db->prepare("
      INSERT INTO wishlist (user_id, product_id, created_at)
      VALUES (:user_id, :product_id, NOW())
  ");

    return $stmt->execute([
      'user_id' => $userId,
      'product_id' => $productId
    ]);
  }

  public function remove(int $userId, int $productId): bool
  {
    $stmt = $this->db->prepare("
            DELETE FROM wishlist 
            WHERE user_id = :user_id AND product_id = :product_id
        ");
    return $stmt->execute([
      'user_id' => $userId,
      'product_id' => $productId
    ]);
  }

  public function exists(int $userId, int $productId): bool
  {
    $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM wishlist 
            WHERE user_id = :user_id AND product_id = :product_id
        ");
    $stmt->execute([
      'user_id' => $userId,
      'product_id' => $productId
    ]);
    return (int) $stmt->fetchColumn() > 0;
  }

  public function getAll(int $userId): array
  {
    $stmt = $this->db->prepare("
            SELECT 
                w.id, w.created_at AS added_at,
                p.id AS product_id, p.name, p.slug,
                p.price, p.compare_price, p.stock, p.is_active,
                (SELECT image_url FROM product_images 
                 WHERE product_id = p.id AND is_primary = 1 
                 LIMIT 1) AS image
            FROM wishlist w
            JOIN products p ON p.id = w.product_id
            WHERE w.user_id = :user_id
            ORDER BY w.created_at DESC
        ");
    $stmt->execute(['user_id' => $userId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}