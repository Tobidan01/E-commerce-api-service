<?php

namespace App\Services;

use App\Config\Database;
use App\Models\ProductsModel;
use App\Models\CategoryModel;
use App\Helpers\Validator;

class ProductsService
{
  private ProductsModel $model;
  private CategoryModel $categoryModel;

  public function __construct(array $config)
  {
    $db = (new Database($config))->connect();
    $this->model = new ProductsModel($db);
    $this->categoryModel = new CategoryModel($db);
  }

  /* ===============================
   | GET ALL (ADMIN)
   =============================== */
  public function getAll(): array
  {
    return $this->model->getAll();
  }

  /* ===============================
   | GET PAGINATED (USER)
   =============================== */
  public function listProducts(array $query): array
  {
    $page = max(1, (int) ($query['page'] ?? 1));
    $limit = max(1, (int) ($query['limit'] ?? 10));
    $offset = ($page - 1) * $limit;

    $sort = $query['sort'] ?? 'newest';
    $search = $query['search'] ?? null;
    $category = isset($query['category']) ? (int) $query['category'] : null;

    $products = $this->model->getPaginated($limit, $offset, $sort, $search, $category);
    $total = $this->model->countActive($search, $category);

    return $this->success("Products retrieved", 200, [
      'data' => $products,
      'pagination' => [
        'page' => $page,
        'limit' => $limit,
        'total' => $total
      ]
    ]);
  }

  /* ===============================
   | FLASH SALES
   =============================== */
  public function getFlashSales(): array
  {
    return $this->model->getFlashSales();
  }

  /* ===============================
   | GET BY ID
   =============================== */
  public function getById(int $id): ?array
  {
    return $this->model->getById($id);
  }

  public function getBySlug(string $slug): ?array
  {
    return $this->model->getBySlug($slug);
  }

  /* ===============================
   | CREATE
   =============================== */
  public function create(array $data): array
  {
    $errors = Validator::required($data, ['name', 'price', 'stock', 'category_id', 'description']);
    if ($errors)
      return $this->fail('Validation failed', 400, $errors);

    $numErrors = Validator::numeric($data, ['price', 'stock', 'category_id']);
    if ($numErrors)
      return $this->fail('Validation failed', 400, $numErrors);

    if ($data['stock'] < 0)
      return $this->fail('Stock cannot be negative', 400);

    if (!$this->categoryModel->exists((int) $data['category_id'])) {
      return $this->fail('Invalid category', 400);
    }

    if (!empty($data['is_flash']) && empty($data['flash_ends_at'])) {
      return $this->fail('Flash sale requires end date', 400);
    }

    $data['slug'] = $this->generateSlug($data['name']);
    $data['is_active'] = $data['is_active'] ?? 'active';

    $id = $this->model->create($data);
    return $this->success('Product created', 201, $this->model->getById($id));
  }

  /* ===============================
   | UPDATE (PATCH STYLE)
   =============================== */
  public function update(int $id, array $data): array
  {
    $existing = $this->model->getById($id);
    if (!$existing)
      return $this->fail('Product not found', 404);

    // merge existing + new
    $data = array_merge($existing, $data);

    $numErrors = Validator::numeric($data, ['price', 'stock', 'category_id']);
    if ($numErrors)
      return $this->fail('Validation failed', 400, $numErrors);

    if ($data['stock'] < 0)
      return $this->fail('Stock cannot be negative', 400);

    if (!$this->categoryModel->exists((int) $data['category_id'])) {
      return $this->fail('Invalid category', 400);
    }

    if (isset($data['name'])) {
      $data['slug'] = $this->generateSlug($data['name']);
    }

    $this->model->update($id, $data);

    return $this->success('Product updated', 200, $this->model->getById($id));
  }

  /* ===============================
   | DELETE
   =============================== */
  public function delete(int $id): array
  {
    if (!$this->model->getById($id)) {
      return $this->fail('Product not found', 404);
    }

    $this->model->delete($id);
    return $this->success('Product deleted', 200);
  }

  /* ===============================
   | HELPERS
   =============================== */
  private function generateSlug(string $name): string
  {
    $base = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $base = trim(preg_replace('/-+/', '-', $base), '-');

    $slug = $base;
    $i = 1;

    while ($this->model->getBySlug($slug)) {
      $slug = $base . '-' . $i++;
    }

    return $slug;
  }

  private function success($msg, $code, $data = null): array
  {
    return ['success' => true, 'message' => $msg, 'code' => $code, 'data' => $data];
  }

  private function fail($msg, $code, $errors = []): array
  {
    return ['success' => false, 'message' => $msg, 'code' => $code, 'data' => $errors ?: null];
  }
}