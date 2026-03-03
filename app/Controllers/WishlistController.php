<?php

namespace App\Controllers;

use App\Services\WishlistService;
use App\Helpers\Response;
use App\Helpers\JwtAuth;

class WishlistController
{
  private WishlistService $service;

  public function __construct(WishlistService $service)
  {
    $this->service = $service;
  }

  // GET /api/wishlist
  public function index(): void
  {
    $user = JwtAuth::requireAuth();
    $items = $this->service->getAll((int) $user['sub']);
    http_response_code(200);
    Response::json(true, "Wishlist retrieved", $items);
  }

  // POST /api/wishlist/{productId}
  public function add(int $productId): void
  {
    $user = JwtAuth::requireAuth();
    $result = $this->service->add((int) $user['sub'], $productId);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message']);
  }

  // DELETE /api/wishlist/{productId}
  public function remove(int $productId): void
  {
    $user = JwtAuth::requireAuth();
    $result = $this->service->remove((int) $user['sub'], $productId);
    http_response_code($result['code']);
    Response::json($result['success'], $result['message']);
  }
}