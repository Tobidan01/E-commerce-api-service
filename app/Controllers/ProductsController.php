<?php

namespace App\Controllers;

use App\Services\ProductsService;
use App\Helpers\Response;
use App\Helpers\JwtAuth;

class ProductsController
{
  private ProductsService $service;

  public function __construct(ProductsService $service)
  {
    $this->service = $service;
  }

  // GET /api/products
  public function index(): void
  {
    $result = $this->service->getAll();
    http_response_code(200);
    Response::json(true, "Products retrieved", $result);
  }

  // GET /api/products/list?page=1&limit=10&sort=newest&search=&category=
  public function listProducts(): void
  {
    $result = $this->service->listProducts($_GET);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data']);
  }

  // GET /api/products/flash-sales
  public function flashSales(): void
  {
    $result = $this->service->getFlashSales();
    http_response_code(200);
    Response::json(true, "Flash sales retrieved", $result);
  }

  // GET /api/products/id/{id}
  public function getById(int $id): void
  {
    $result = $this->service->getById($id);

    if (!$result) {
      http_response_code(404);
      Response::json(false, "Product not found");
      return;
    }

    http_response_code(200);
    Response::json(true, "Product retrieved", $result);
  }

  // GET /api/products/slug/{slug}
  public function getBySlug(string $slug): void
  {
    $result = $this->service->getBySlug($slug);

    if (!$result) {
      http_response_code(404);
      Response::json(false, "Product not found");
      return;
    }

    http_response_code(200);
    Response::json(true, "Product retrieved", $result);
  }

  // POST /api/products (admin only)
  public function create(): void
  {
    $input = json_decode(file_get_contents("php://input"), true);
    $result = $this->service->create($input ?? []);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  // PUT /api/products/{id} (admin only)
  public function update(int $id): void
  {
    $input = json_decode(file_get_contents("php://input"), true);
    $result = $this->service->update($id, $input ?? []);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  // DELETE /api/products/{id} (admin only)
  public function delete(int $id): void
  {
    $result = $this->service->delete($id);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message']);
  }
}