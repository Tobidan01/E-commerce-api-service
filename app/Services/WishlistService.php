<?php

namespace App\Services;

use App\Config\Database;
use App\Models\WishlistModel;
use App\Models\ProductsModel;

class WishlistService
{
    private WishlistModel $wishlistModel;
    private ProductsModel $productsModel;

    public function __construct(array $config)
    {
        $db = (new Database($config))->connect();

        $this->wishlistModel = new WishlistModel($db);
        $this->productsModel = new ProductsModel($db);
    }

    public function getAll(int $userId): array
    {
        return $this->wishlistModel->getAll($userId);
    }

    public function add(int $userId, int $productId): array
    {
        if (!$this->productsModel->getById($productId)) {
            return $this->fail('Product not found', 404);
        }

        if ($this->wishlistModel->exists($userId, $productId)) {
            return $this->fail('Already in wishlist', 409);
        }

        $this->wishlistModel->add($userId, $productId);

        return $this->success('Added to wishlist', 201);
    }

    public function remove(int $userId, int $productId): array
    {
        if (!$this->wishlistModel->exists($userId, $productId)) {
            return $this->fail('Not in wishlist', 404);
        }

        $this->wishlistModel->remove($userId, $productId);

        return $this->success('Removed from wishlist', 200);
    }

    private function success($msg, $code): array
    {
        return ['success' => true, 'message' => $msg, 'code' => $code];
    }

    private function fail($msg, $code): array
    {
        return ['success' => false, 'message' => $msg, 'code' => $code];
    }
}