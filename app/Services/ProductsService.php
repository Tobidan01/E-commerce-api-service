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

  public function create(array $data): array
  {
    $errors = Validator::required($data, ['name', 'price', 'stock', 'category_id']);
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
      return $this->fail('Flash sale requires an end date', 400);
    }

    $data['is_active'] = $data['is_active'] ?? 'active';
    $data['slug'] = $this->generateSlug($data['name']);

    $id = $this->model->create($data);
    $product = $this->model->getById($id);

    return $this->success('Product created', 201, $this->formatProduct($product));
  }

  public function update(int $id, array $data): array
  {
    if (!$this->model->getById($id)) {
      return $this->fail('Product not found', 404);
    }

    $numErrors = Validator::numeric($data, ['price', 'stock', 'category_id']);
    if ($numErrors)
      return $this->fail('Validation failed', 400, $numErrors);

    if (isset($data['stock']) && $data['stock'] < 0) {
      return $this->fail('Stock cannot be negative', 400);
    }

    if (isset($data['category_id']) && !$this->categoryModel->exists((int) $data['category_id'])) {
      return $this->fail('Invalid category', 400);
    }

    if (isset($data['name'])) {
      $data['slug'] = $this->generateSlug($data['name']);
    }

    $this->model->update($id, $data);

    return $this->success('Product updated', 200, $this->formatProduct($this->model->getById($id)));
  }

  public function delete(int $id): array
  {
    if (!$this->model->getById($id)) {
      return $this->fail('Product not found', 404);
    }

    $this->model->delete($id);
    return $this->success('Product deleted', 200);
  }

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

  private function formatProduct(array $p): array
  {
    $p['price'] = (float) $p['price'];
    $p['stock'] = (int) $p['stock'];
    return $p;
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