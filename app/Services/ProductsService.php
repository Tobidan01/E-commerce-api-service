<?php

namespace App\Services;

use App\Config\Database;
use App\Models\ProductsModel;
use App\Models\CategoryModel;
use App\Helpers\JwtAuth;
use App\Helpers\Validator;

class ProductsService
{
  private ProductsModel $model;
  private CategoryModel $categoryModel;

  private array $config;

  public function __construct(array $config)
  {
    $this->config = $config;

    // Pass config into Database
    $db = (new Database($config))->connect();

    $this->model = new ProductsModel($db);
    $this->categoryModel = new CategoryModel($db);
  }

  /*--------------------------------------------------------------
   | GET FLASH SALES
   --------------------------------------------------------------*/
  public function getFlashSales(): array
  {
    $products = $this->model->getFlashSales();
    return array_map(fn($p) => $this->formatProduct($p), $products);
  }

  /*--------------------------------------------------------------
   | CREATE PRODUCT
   --------------------------------------------------------------*/
  public function create(array $data): array
  {
    JwtAuth::requireAdmin();

    $errors = Validator::required($data, ['name', 'price', 'stock', 'category_id']);
    if (!empty($errors)) {
      return $this->fail('Validation failed', 400, $errors);
    }

    $numErrors = Validator::numeric($data, ['price', 'stock', 'category_id']);
    if (!empty($numErrors)) {
      return $this->fail('Validation failed', 400, $numErrors);
    }

    if ($data['stock'] < 0) {
      return $this->fail('Stock cannot be negative', 400);
    }

    if (!$this->categoryModel->exists((int) $data['category_id'])) {
      return $this->fail('Invalid category', 400);
    }

    if (isset($data['is_active']) && !in_array($data['is_active'], ['active', 'inactive'])) {
      return $this->fail('Invalid status value', 400);
    }

    if (!empty($data['is_flash']) && empty($data['flash_ends_at'])) {
      return $this->fail('Flash sale requires an end date', 400);
    }

    $data['is_active'] = $data['is_active'] ?? 'active';
    $data['slug'] = $this->generateSlug($data['name']);

    $id = $this->model->create($data);
    $product = $this->model->getById($id);

    return $this->success('Product created', 201, $this->formatProduct($product));
  }

  /*--------------------------------------------------------------
   | UPDATE PRODUCT
   --------------------------------------------------------------*/
  public function update(int $id, array $data): array
  {
    JwtAuth::requireAdmin();

    $exists = $this->model->getById($id);
    if (!$exists) {
      return $this->fail('Product not found', 404);
    }

    $numErrors = Validator::numeric($data, ['price', 'stock', 'category_id']);
    if (!empty($numErrors)) {
      return $this->fail('Validation failed', 400, $numErrors);
    }

    if (isset($data['stock']) && $data['stock'] < 0) {
      return $this->fail('Stock cannot be negative', 400);
    }

    if (isset($data['category_id']) && !$this->categoryModel->exists((int) $data['category_id'])) {
      return $this->fail('Invalid category', 400);
    }

    if (isset($data['is_active']) && !in_array($data['is_active'], ['active', 'inactive'])) {
      return $this->fail('Invalid status value', 400);
    }

    if (isset($data['name'])) {
      $data['slug'] = $this->generateSlug($data['name']);
    }

    $this->model->update($id, $data);

    return $this->success('Product updated', 200, $this->formatProduct($this->model->getById($id)));
  }

  /*--------------------------------------------------------------
   | DELETE PRODUCT
   --------------------------------------------------------------*/
  public function delete(int $id): array
  {
    JwtAuth::requireAdmin();

    if (!$this->model->getById($id)) {
      return $this->fail('Product not found', 404);
    }

    $this->model->delete($id);
    return $this->success('Product deleted', 200);
  }

  /*--------------------------------------------------------------
   | LIST PRODUCTS
   --------------------------------------------------------------*/
  public function listProducts(array $query): array
  {
    $page = max(1, (int) ($query['page'] ?? 1));
    $limit = min(50, max(1, (int) ($query['limit'] ?? 10)));
    $offset = ($page - 1) * $limit;
    $sort = $query['sort'] ?? 'newest';
    $search = $query['search'] ?? null;
    $category = isset($query['category']) ? (int) $query['category'] : null;

    $products = $this->model->getPaginated($limit, $offset, $sort, $search, $category);
    $total = $this->model->countActive($search, $category);
    $products = array_map(fn($p) => $this->formatProduct($p), $products);

    return $this->success('Products retrieved', 200, [
      'products' => $products,
      'page' => $page,
      'limit' => $limit,
      'total' => $total,
      'pages' => (int) ceil($total / $limit)
    ]);
  }

  /*--------------------------------------------------------------
   | GET BY ID / SLUG
   --------------------------------------------------------------*/
  public function getById(int $id): ?array
  {
    $product = $this->model->getById($id);
    return $product ? $this->formatProduct($product) : null;
  }

  public function getBySlug(string $slug): ?array
  {
    $product = $this->model->getBySlug($slug);
    return $product ? $this->formatProduct($product) : null;
  }

  /*--------------------------------------------------------------
   | GET ALL
   --------------------------------------------------------------*/
  public function getAll(): array
  {
    $products = $this->model->getAllActive();
    return array_map(fn($p) => $this->formatProduct($p), $products);
  }

  public function getAllAdmin(): array
  {
    $products = $this->model->getAll();
    return array_map(fn($p) => $this->formatProduct($p), $products);
  }

  /*--------------------------------------------------------------
   | FORMAT PRODUCT
   --------------------------------------------------------------*/
  private function formatProduct(array $product): array
  {
    $product['price'] = (float) $product['price'];
    $product['compare_price'] = (float) ($product['compare_price'] ?? 0);
    $product['cost_price'] = (float) ($product['cost_price'] ?? 0);
    $product['stock'] = (int) $product['stock'];
    $product['is_flash'] = (bool) $product['is_flash'];
    $product['discount'] = (int) ($product['discount'] ?? 0);
    $product['avg_rating'] = round((float) ($product['avg_rating'] ?? 0), 1);
    $product['review_count'] = (int) ($product['review_count'] ?? 0);

    $product['has_discount'] = $product['compare_price'] > $product['price'];

    if ($product['has_discount']) {
      $product['discount_amount'] = round($product['compare_price'] - $product['price'], 2);
      $product['discount_percent'] = $product['discount'] > 0
        ? $product['discount']
        : round(($product['discount_amount'] / $product['compare_price']) * 100);
    } else {
      $product['discount_amount'] = 0;
      $product['discount_percent'] = 0;
    }

    $product['profit'] = round($product['price'] - $product['cost_price'], 2);
    $product['margin_percent'] = $product['price'] > 0
      ? round(($product['profit'] / $product['price']) * 100)
      : 0;

    $product['price_label'] = $product['has_discount']
      ? "Was ₦{$product['compare_price']}, now ₦{$product['price']}"
      : "₦{$product['price']}";

    $product['stock_label'] = match (true) {
      $product['stock'] === 0 => 'Out of stock',
      $product['stock'] <= 5 => 'Only ' . $product['stock'] . ' left',
      default => 'In stock'
    };

    return $product;
  }

  /*--------------------------------------------------------------
   | SLUG GENERATOR
   --------------------------------------------------------------*/
  private function generateSlug(string $name): string
  {
    $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $baseSlug = preg_replace('/-+/', '-', $baseSlug);
    $baseSlug = trim($baseSlug, '-');

    $slug = $baseSlug;
    $counter = 1;

    while ($this->model->getBySlug($slug)) {
      $slug = $baseSlug . '-' . $counter;
      $counter++;
    }

    return $slug;
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
