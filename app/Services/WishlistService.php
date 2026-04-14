<?php

namespace App\Services;

use App\Config\Database;
use App\Models\WishlistModel;
use App\Models\ProductsModel;

class WishlistService
{
  private WishlistModel $wishlistModel;
  private ProductsModel $productsModel;
  private array $config;

  public function __construct(array $config)
  {
    $this->config = $config;

    // Pass config into Database
    $db = (new Database($config))->connect();

    $this->wishlistModel = new WishlistModel($db);
    $this->productsModel = new ProductsModel($db);
  }

  public function getAll(int $userId): array
  {
    $items = $this->wishlistModel->getAll($userId);

    return array_map(function ($item) {
      $item['price'] = (float) $item['price'];
      $item['compare_price'] = (float) ($item['compare_price'] ?? 0);
      $item['in_stock'] = (int) $item['stock'] > 0;
      return $item;
    }, $items);
  }

  public function add(int $userId, int $productId): array
  {
    $product = $this->productsModel->getById($productId);

    if (!$product) {
      return $this->fail('Product not found', 404);
    }

    if ($this->wishlistModel->exists($userId, $productId)) {
      return $this->fail('Already in wishlist', 409);
    }

    $added = $this->wishlistModel->add($userId, $productId);

    if (!$added) {
      return $this->fail('Database error', 500);
    }

    return $this->success('Added to wishlist', 201);
  }
  public function remove(int $userId, int $productId): array
  {
    if (!$this->wishlistModel->exists($userId, $productId)) {
      return $this->fail('Not in wishlist', 404);
    }

    $deleted = $this->wishlistModel->remove($userId, $productId);

    if (!$deleted) {
      return $this->fail('Database error', 500);
    }

    return $this->success('Removed from wishlist', 200);
  }
  private function success(string $message, int $code, ?array $data = null): array
  {
    return ['success' => true, 'message' => $message, 'code' => $code, 'data' => $data];
  }

  private function fail(string $message, int $code): array
  {
    return ['success' => false, 'message' => $message, 'code' => $code, 'data' => null];
  }
}
