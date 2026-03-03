<?php

namespace App\Controllers;

use App\Services\CartService;
use App\Helpers\Response;
use App\Helpers\JwtAuth;

class CartController
{
  private CartService $service;

  public function __construct(CartService $service)
  {
    $this->service = $service;
  }

  // GET /api/cart
  public function getCart(): void
  {
    $user = JwtAuth::requireAuth();
    $cart = $this->service->getCart((int) $user['sub']);
    http_response_code(200);
    Response::json(true, "Cart retrieved", $cart);
  }

  // POST /api/cart/add
  public function addToCart(): void
  {
    $user = JwtAuth::requireAuth();
    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
      http_response_code(400);
      Response::json(false, "Invalid JSON body");
      return;
    }

    $result = $this->service->addToCart((int) $user['sub'], $input);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  // PUT /api/cart/{id}
  public function updateItem(int $id): void
  {
    $user = JwtAuth::requireAuth();
    $input = json_decode(file_get_contents("php://input"), true);

    if (!is_array($input)) {
      http_response_code(400);
      Response::json(false, "Invalid JSON body");
      return;
    }

    $result = $this->service->updateItem((int) $user['sub'], $id, $input);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message'], $result['data'] ?? null);
  }

  // DELETE /api/cart/{id}
  public function removeItem(int $id): void
  {
    $user = JwtAuth::requireAuth();
    $result = $this->service->removeItem((int) $user['sub'], $id);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message']);
  }

  // DELETE /api/cart
  public function clearCart(): void
  {
    $user = JwtAuth::requireAuth();
    $result = $this->service->clearCart((int) $user['sub']);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message']);
  }
}