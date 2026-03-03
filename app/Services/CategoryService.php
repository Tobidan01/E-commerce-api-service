<?php

namespace App\Services;

use App\Config\Database;
use App\Models\CategoryModel;
use App\Helpers\Validator;
use App\Helpers\JwtAuth;

class CategoryService
{
  private CategoryModel $model;

  public function __construct(array $config)
  {
    $db = (new Database($config))->connect();
    $this->model = new CategoryModel($db);
  }

  public function getAll(): array
  {
    $flat = $this->model->getWithProductCount();
    return $this->nestCategories($flat);
  }

  public function getById(int $id): ?array
  {
    return $this->model->getById($id);
  }

  public function create(array $data): array
  {
    JwtAuth::requireAdmin();

    $errors = Validator::required($data, ['name']);
    if (!empty($errors)) {
      return $this->fail('Validation failed', 400, $errors);
    }

    $nameError = Validator::minLength($data, 'name', 3);
    if ($nameError) {
      return $this->fail($nameError, 400);
    }

    if (!empty($data['parent_id']) && !$this->model->exists((int) $data['parent_id'])) {
      return $this->fail('Parent category not found', 400);
    }

    $data['slug'] = $this->generateSlug($data['name']);

    $id = $this->model->create($data);
    return $this->success('Category created', 201, $this->model->getById($id));
  }

  public function update(int $id, array $data): array
  {
    JwtAuth::requireAdmin();

    if (!$this->model->getById($id)) {
      return $this->fail('Category not found', 404);
    }

    $errors = Validator::required($data, ['name']);
    if (!empty($errors)) {
      return $this->fail('Validation failed', 400, $errors);
    }

    if (!empty($data['parent_id']) && (int) $data['parent_id'] === $id) {
      return $this->fail('Category cannot be its own parent', 400);
    }

    if (!empty($data['parent_id']) && !$this->model->exists((int) $data['parent_id'])) {
      return $this->fail('Parent category not found', 400);
    }

    $data['slug'] = $this->generateSlug($data['name']);

    $this->model->update($id, $data);
    return $this->success('Category updated', 200, $this->model->getById($id));
  }

  public function delete(int $id): array
  {
    JwtAuth::requireAdmin();

    if (!$this->model->getById($id)) {
      return $this->fail('Category not found', 404);
    }

    $this->model->delete($id);
    return $this->success('Category deleted', 200);
  }

  private function nestCategories(array $categories): array
  {
    $nested = [];
    $map = [];

    foreach ($categories as $cat) {
      $cat['children'] = [];
      $map[$cat['id']] = $cat;
    }

    foreach ($map as $id => $cat) {
      if ($cat['parent_id']) {
        $map[$cat['parent_id']]['children'][] = &$map[$id];
      } else {
        $nested[] = &$map[$id];
      }
    }

    return $nested;
  }

  private function generateSlug(string $name): string
  {
    $baseSlug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $name)));
    $baseSlug = trim(preg_replace('/-+/', '-', $baseSlug), '-');

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
