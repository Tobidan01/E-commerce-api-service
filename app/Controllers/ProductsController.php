<?php

namespace App\Controllers;

use App\Services\ProductsService;
use App\Helpers\Response;

class ProductsController
{
  private ProductsService $service;

  public function __construct(ProductsService $service)
  {
    $this->service = $service;
  }

  public function index(): void
  {
    Response::json(true, "Products retrieved", $this->service->getAll());
  }

  public function listProducts(): void
  {
    $result = $this->service->listProducts($_GET);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data']);
  }

  public function flashSales(): void
  {
    Response::json(true, "Flash sales", $this->service->getFlashSales());
  }

  public function getById(int $id): void
  {
    $product = $this->service->getById($id);

    if (!$product) {
      http_response_code(404);
      Response::json(false, "Not found");
      return;
    }

    Response::json(true, "Product", $product);
  }

  public function getBySlug(string $slug): void
  {
    $product = $this->service->getBySlug($slug);

    if (!$product) {
      http_response_code(404);
      Response::json(false, "Not found");
      return;
    }

    Response::json(true, "Product", $product);
  }

  public function create(): void
  {
    $input = json_decode(file_get_contents("php://input"), true);
    $result = $this->service->create($input ?? []);

    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data']);
  }

  public function update(int $id): void
  {
    $input = json_decode(file_get_contents("php://input"), true);
    $result = $this->service->update($id, $input ?? []);

    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data']);
  }

  public function delete(int $id): void
  {
    $result = $this->service->delete($id);

    http_response_code($result['code']);
    Response::json($result['success'], $result['message']);
  }
}