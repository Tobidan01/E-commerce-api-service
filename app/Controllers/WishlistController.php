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

    $result = $this->service->getAll((int) $user['sub']);

    Response::json(
      $result['success'],
      $result['message'],
      $result['data'],
      $result['code']
    );
  }

  // POST /api/wishlist/{productId}
  public function add(int $productId): void
  {
    $user = JwtAuth::requireAuth();

    $result = $this->service->add((int) $user['sub'], $productId);

    Response::json(
      $result['success'],
      $result['message'],
      $result['data'],
      $result['code']
    );
  }

  // DELETE /api/wishlist/{productId}
  public function remove(int $productId): void
  {
    $user = JwtAuth::requireAuth();

    $result = $this->service->remove((int) $user['sub'], $productId);

    Response::json(
      $result['success'],
      $result['message'],
      $result['data'],
      $result['code']
    );
  }
}