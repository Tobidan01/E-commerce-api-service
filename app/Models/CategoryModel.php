<?php

namespace App\Models;

use PDO;

class CategoryModel
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  // Get all categories with subcategories nested
  public function getAll(): array
  {
    $stmt = $this->db->query("
            SELECT c.*,
                p.name AS parent_name
            FROM categories c
            LEFT JOIN categories p ON p.id = c.parent_id
            ORDER BY c.parent_id ASC, c.name ASC
        ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  public function getById(int $id): ?array
  {
    $stmt = $this->db->prepare("
            SELECT c.*, p.name AS parent_name
            FROM categories c
            LEFT JOIN categories p ON p.id = c.parent_id
            WHERE c.id = ?
            LIMIT 1
        ");
    $stmt->execute([$id]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public function getBySlug(string $slug): ?array
  {
    $stmt = $this->db->prepare("
            SELECT * FROM categories WHERE slug = ? LIMIT 1
        ");
    $stmt->execute([$slug]);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
  }

  public function exists(int $id): bool
  {
    $stmt = $this->db->prepare("SELECT id FROM categories WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    return (bool) $stmt->fetchColumn();
  }

  public function create(array $data): int
  {
    $stmt = $this->db->prepare("
            INSERT INTO categories (name, slug, parent_id, created_at)
            VALUES (:name, :slug, :parent_id, NOW())
        ");

    $stmt->execute([
      ':name' => $data['name'],
      ':slug' => $data['slug'],
      ':parent_id' => $data['parent_id'] ?? null
    ]);

    return (int) $this->db->lastInsertId();
  }

  public function update(int $id, array $data): void
  {
    $stmt = $this->db->prepare("
            UPDATE categories
            SET name       = :name,
                slug       = :slug,
                parent_id  = :parent_id,
                updated_at = NOW()
            WHERE id = :id
        ");

    $stmt->execute([
      ':name' => $data['name'],
      ':slug' => $data['slug'],
      ':parent_id' => $data['parent_id'] ?? null,
      ':id' => $id
    ]);
  }

  public function delete(int $id): void
  {
    $stmt = $this->db->prepare("DELETE FROM categories WHERE id = ?");
    $stmt->execute([$id]);
  }

  // Get products count per category
  public function getWithProductCount(): array
  {
    $stmt = $this->db->query("
            SELECT c.*,
                p.name AS parent_name,
                COUNT(pr.id) AS product_count
            FROM categories c
            LEFT JOIN categories p ON p.id = c.parent_id
            LEFT JOIN products pr ON pr.category_id = c.id AND pr.is_active = 'active'
            GROUP BY c.id
            ORDER BY c.parent_id ASC, c.name ASC
        ");
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }
}