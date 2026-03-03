<?php

namespace App\Controllers;

use App\Services\CategoryService;
use App\Helpers\Response;

class CategoryController
{
  private CategoryService $service;

  public function __construct(CategoryService $service)
  {
    $this->service = $service;
  }

  // GET /api/categories
  public function index(): void
  {
    $categories = $this->service->getAll();
    http_response_code(200);
    Response::json(true, "Categories retrieved", $categories);
  }

  // GET /api/categories/{id}
  public function getById(int $id): void
  {
    $category = $this->service->getById($id);

    if (!$category) {
      http_response_code(404);
      Response::json(false, "Category not found");
      return;
    }

    http_response_code(200);
    Response::json(true, "Category retrieved", $category);
  }

  // POST /api/categories (admin only)
  public function create(): void
  {
    $input = json_decode(file_get_contents("php://input"), true);
    $result = $this->service->create($input ?? []);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  // PUT /api/categories/{id} (admin only)
  public function update(int $id): void
  {
    $input = json_decode(file_get_contents("php://input"), true);
    $result = $this->service->update($id, $input ?? []);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  // DELETE /api/categories/{id} (admin only)
  public function delete(int $id): void
  {
    $result = $this->service->delete($id);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message']);
  }
}