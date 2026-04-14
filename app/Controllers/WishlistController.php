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

  // POST /api/wishlist/{id}
  public function add(int $id): void
  {
    $user = JwtAuth::requireAuth();
    $result = $this->service->add((int) $user['sub'], $id);

    Response::json(
      $result['success'],
      $result['message'],
      $result['data'],
      $result['code']
    );
  }

  // DELETE /api/wishlist/{id}
  public function remove(int $id): void
  {
    $user = JwtAuth::requireAuth();
    $result = $this->service->remove((int) $user['sub'], $id);

    Response::json(
      $result['success'],
      $result['message'],
      $result['data'],
      $result['code']
    );
  }
}
