<?php

namespace App\Models;

use PDO;

class ProductsModel
{
  private PDO $db;

  public function __construct(PDO $db)
  {
    $this->db = $db;
  }

  /*--------------------------------------------------------------
   | GET ALL ACTIVE (USER)
   --------------------------------------------------------------*/
  public function getAllActive(): array
  {
    $sql = "SELECT p.*,
                    (SELECT image_url FROM product_images 
                     WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
                    COALESCE(AVG(r.rating), 0) AS avg_rating,
                    COUNT(r.id) AS review_count
                FROM products p
                LEFT JOIN product_reviews r ON r.product_id = p.id
                WHERE p.is_active = 'active'
                GROUP BY p.id
                ORDER BY p.id DESC";

    return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  /*--------------------------------------------------------------
   | GET ALL (ADMIN)
   --------------------------------------------------------------*/
  public function getAll(): array
  {
    $sql = "SELECT p.*,
                    (SELECT image_url FROM product_images 
                     WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image
                FROM products p
                ORDER BY p.id DESC";

    return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  /*--------------------------------------------------------------
   | GET FLASH SALES
   --------------------------------------------------------------*/
  public function getFlashSales(): array
  {
    $sql = "SELECT p.*,
                    (SELECT image_url FROM product_images 
                     WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
                    COALESCE(AVG(r.rating), 0) AS avg_rating,
                    COUNT(r.id) AS review_count
                FROM products p
                LEFT JOIN product_reviews r ON r.product_id = p.id
                WHERE p.is_active = 'active'
                AND p.is_flash = 1
                AND (p.flash_ends_at IS NULL OR p.flash_ends_at > NOW())
                GROUP BY p.id
                ORDER BY p.created_at DESC";

    return $this->db->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  }

  /*--------------------------------------------------------------
   | PAGINATED LIST
   --------------------------------------------------------------*/
  public function getPaginated(
    int $limit,
    int $offset,
    string $sort,
    ?string $search,
    ?int $category
  ): array {
    $sql = "SELECT p.*,
                    (SELECT image_url FROM product_images 
                     WHERE product_id = p.id AND is_primary = 1 LIMIT 1) AS primary_image,
                    COALESCE(AVG(r.rating), 0) AS avg_rating,
                    COUNT(r.id) AS review_count
                FROM products p
                LEFT JOIN product_reviews r ON r.product_id = p.id
                WHERE p.is_active = 'active'";

    $params = [];

    if ($search !== null && $search !== '') {
      $sql .= " AND (
                LOWER(p.name) LIKE :search_name
                OR LOWER(COALESCE(p.description,'')) LIKE :search_desc
            )";
      $params['search_name'] = '%' . strtolower($search) . '%';
      $params['search_desc'] = '%' . strtolower($search) . '%';
    }

    if ($category !== null) {
      $sql .= " AND p.category_id = :category";
      $params['category'] = $category;
    }

    $sql .= " GROUP BY p.id";

    switch ($sort) {
      case 'price_asc':
        $sql .= " ORDER BY p.price ASC";
        break;
      case 'price_desc':
        $sql .= " ORDER BY p.price DESC";
        break;
      case 'popular':
        $sql .= " ORDER BY review_count DESC";
        break;
      default:
        $sql .= " ORDER BY p.created_at DESC";
    }

    $sql .= " LIMIT $limit OFFSET $offset";

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /*--------------------------------------------------------------
   | COUNT ACTIVE (for pagination)
   --------------------------------------------------------------*/
  public function countActive(?string $search, ?int $category): int
  {
    $sql = "SELECT COUNT(*) FROM products WHERE is_active = 'active'";
    $params = [];

    if ($search !== null && $search !== '') {
      $sql .= " AND (
                LOWER(name) LIKE :search_name
                OR LOWER(COALESCE(description,'')) LIKE :search_desc
            )";
      $params['search_name'] = '%' . strtolower($search) . '%';
      $params['search_desc'] = '%' . strtolower($search) . '%';
    }

    if ($category !== null) {
      $sql .= " AND category_id = :category";
      $params['category'] = $category;
    }

    $stmt = $this->db->prepare($sql);
    $stmt->execute($params);

    return (int) $stmt->fetchColumn();
  }

  /*--------------------------------------------------------------
   | GET BY ID (with images and variants)
   --------------------------------------------------------------*/
  public function getById(int $id): ?array
  {
    $stmt = $this->db->prepare("
            SELECT p.*,
                COALESCE(AVG(r.rating), 0) AS avg_rating,
                COUNT(r.id) AS review_count
            FROM products p
            LEFT JOIN product_reviews r ON r.product_id = p.id
            WHERE p.id = ?
            GROUP BY p.id
            LIMIT 1
        ");
    $stmt->execute([$id]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product)
      return null;

    // Attach images
    $product['images'] = $this->getImages($id);

    // Attach variants
    $product['variants'] = $this->getVariants($id);

    return $product;
  }

  /*--------------------------------------------------------------
   | GET BY SLUG (with images and variants)
   --------------------------------------------------------------*/
  public function getBySlug(string $slug): ?array
  {
    $stmt = $this->db->prepare("
            SELECT p.*,
                COALESCE(AVG(r.rating), 0) AS avg_rating,
                COUNT(r.id) AS review_count
            FROM products p
            LEFT JOIN product_reviews r ON r.product_id = p.id
            WHERE p.slug = ?
            GROUP BY p.id
            LIMIT 1
        ");
    $stmt->execute([$slug]);
    $product = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$product)
      return null;

    $product['images'] = $this->getImages($product['id']);
    $product['variants'] = $this->getVariants($product['id']);

    return $product;
  }

  /*--------------------------------------------------------------
   | GET PRODUCT IMAGES
   --------------------------------------------------------------*/
  public function getImages(int $productId): array
  {
    $stmt = $this->db->prepare("
            SELECT id, image_url, is_primary, sort_order
            FROM product_images
            WHERE product_id = ?
            ORDER BY is_primary DESC, sort_order ASC
        ");
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /*--------------------------------------------------------------
   | GET PRODUCT VARIANTS
   --------------------------------------------------------------*/
  public function getVariants(int $productId): array
  {
    $stmt = $this->db->prepare("
            SELECT id, size, color, stock
            FROM product_variants
            WHERE product_id = ?
        ");
    $stmt->execute([$productId]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
  }

  /*--------------------------------------------------------------
   | CREATE PRODUCT
   --------------------------------------------------------------*/
  public function create(array $data): int
  {
    $stmt = $this->db->prepare("
            INSERT INTO products (
                name, slug, description, short_description,
                price, compare_price, cost_price, stock,
                is_active, category_id, is_flash, flash_ends_at, discount
            )
            VALUES (
                :name, :slug, :description, :short_description,
                :price, :compare_price, :cost_price, :stock,
                :is_active, :category_id, :is_flash, :flash_ends_at, :discount
            )
        ");

    $stmt->execute([
      ':name' => $data['name'],
      ':slug' => $data['slug'],
      ':description' => $data['description'] ?? null,
      ':short_description' => $data['short_description'] ?? null,
      ':price' => $data['price'],
      ':compare_price' => $data['compare_price'] ?? null,
      ':cost_price' => $data['cost_price'] ?? null,
      ':stock' => $data['stock'] ?? 0,
      ':is_active' => $data['is_active'] ?? 'active',
      ':category_id' => $data['category_id'] ?? null,
      ':is_flash' => $data['is_flash'] ?? 0,
      ':flash_ends_at' => $data['flash_ends_at'] ?? null,
      ':discount' => $data['discount'] ?? 0
    ]);

    return (int) $this->db->lastInsertId();
  }

  /*--------------------------------------------------------------
   | UPDATE PRODUCT
   --------------------------------------------------------------*/
  public function update(int $id, array $data): void
  {
    $stmt = $this->db->prepare("
            UPDATE products
            SET name              = :name,
                slug              = :slug,
                description       = :description,
                short_description = :short_description,
                price             = :price,
                compare_price     = :compare_price,
                cost_price        = :cost_price,
                stock             = :stock,
                is_active         = :is_active,
                category_id       = :category_id,
                is_flash          = :is_flash,
                flash_ends_at     = :flash_ends_at,
                discount          = :discount,
                updated_at        = NOW()
            WHERE id = :id
        ");

    $stmt->execute([
      ':name' => $data['name'],
      ':slug' => $data['slug'],
      ':description' => $data['description'] ?? null,
      ':short_description' => $data['short_description'] ?? null,
      ':price' => $data['price'] ?? 0,
      ':compare_price' => $data['compare_price'] ?? null,
      ':cost_price' => $data['cost_price'] ?? null,
      ':stock' => $data['stock'] ?? 0,
      ':is_active' => $data['is_active'] ?? 'active',
      ':category_id' => $data['category_id'] ?? null,
      ':is_flash' => $data['is_flash'] ?? 0,
      ':flash_ends_at' => $data['flash_ends_at'] ?? null,
      ':discount' => $data['discount'] ?? 0,
      ':id' => $id
    ]);
  }

  /*--------------------------------------------------------------
   | DELETE PRODUCT
   --------------------------------------------------------------*/
  public function delete(int $id): void
  {
    $stmt = $this->db->prepare("DELETE FROM products WHERE id = ?");
    $stmt->execute([$id]);
  }

  /*--------------------------------------------------------------
   | REDUCE STOCK
   --------------------------------------------------------------*/
  public function reduceStock(int $productId, int $quantity): bool
  {
    $stmt = $this->db->prepare("
            UPDATE products 
            SET stock = stock - :qty 
            WHERE id = :id AND stock >= :qty
        ");

    return $stmt->execute([
      'qty' => $quantity,
      'id' => $productId
    ]);
  }
}