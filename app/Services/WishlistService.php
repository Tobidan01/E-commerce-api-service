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
        $items = $this->wishlistModel->getAll($userId);

        return [
            'success' => true,
            'message' => 'Wishlist retrieved',
            'code' => 200,
            'data' => $items
        ];
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

    // Fetch the product to return as data
    $product = $this->productsModel->getById($productId);

    return $this->success('Added to wishlist', 201, $product);
}

    public function remove(int $userId, int $productId): array
    {
        if (!$this->wishlistModel->exists($userId, $productId)) {
            return $this->fail('Not in wishlist', 404);
        }

        $this->wishlistModel->remove($userId, $productId);

        return $this->success('Removed from wishlist', 200);
    }

    private function success(string $message, int $code, $data = null): array
    {
        return [
            'success' => true,
            'message' => $message,
            'code' => $code,
            'data' => $data
        ];
    }

    private function fail(string $message, int $code): array
    {
        return [
            'success' => false,
            'message' => $message,
            'code' => $code,
            'data' => null
        ];
    }
}
