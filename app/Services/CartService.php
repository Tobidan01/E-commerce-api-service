<?php

namespace App\Services;

use App\Config\Database;
use App\Models\CartModel;
use App\Models\ProductsModel;
use App\Helpers\Validator;

class CartService
{
  private CartModel $model;
  private ProductsModel $productsModel;
  private array $config;

  public function __construct(array $config)
  {
    $this->config = $config;

    // Pass config into Database
    $db = (new Database($config))->connect();

    $this->model = new CartModel($db);
    $this->productsModel = new ProductsModel($db);
  }

  // GET CART with totals
  public function getCart(int $userId): array
  {
    $items = $this->model->getCart($userId);
    $subtotal = 0;

    $formatted = array_map(function ($item) use (&$subtotal) {
      $item['price'] = (float) $item['price'];
      $item['quantity'] = (int) $item['quantity'];
      $item['subtotal'] = round($item['price'] * $item['quantity'], 2);
      $subtotal += $item['subtotal'];
      return $item;
    }, $items);

    $shipping = $subtotal >= 100 ? 0 : 10;

    return [
      'items' => $formatted,
      'subtotal' => round($subtotal, 2),
      'shipping' => $shipping,
      'total' => round($subtotal + $shipping, 2),
      'count' => count($formatted)
    ];
  }

  // ADD TO CART
  public function addToCart(int $userId, array $data): array
  {
    $errors = Validator::required($data, ['product_id', 'quantity']);
    if (!empty($errors)) {
      return $this->fail('Validation failed', 400, $errors);
    }

    $numErrors = Validator::numeric($data, ['product_id', 'quantity']);
    if (!empty($numErrors)) {
      return $this->fail('Validation failed', 400, $numErrors);
    }

    if ((int) $data['quantity'] < 1) {
      return $this->fail('Quantity must be at least 1', 400);
    }

    $product = $this->productsModel->getById((int) $data['product_id']);
    if (!$product) {
      return $this->fail('Product does not exist', 404);
    }

    if ($product['stock'] <= 0) {
      return $this->fail('Product is out of stock', 400);
    }

    $existing = $this->model->getCartItem($userId, (int) $data['product_id']);
    $requestedQty = (int) $data['quantity'];

    if ($existing) {
      $requestedQty += (int) $existing['quantity'];
    }

    if ($requestedQty > $product['stock']) {
      return $this->fail('Not enough stock available', 400);
    }

    $itemId = $this->model->addToCart($userId, (int) $data['product_id'], (int) $data['quantity']);

    return $this->success('Item added to cart', 200, $this->model->getItem($itemId));
  }

  // UPDATE ITEM
  public function updateItem(int $userId, int $itemId, array $data): array
  {
    // Ensure quantity is provided
    if (!isset($data['quantity'])) {
      return $this->fail('Quantity is required', 400);
    }

    // Ensure quantity is numeric
    if (!is_numeric($data['quantity'])) {
      return $this->fail('Quantity must be a number', 400);
    }

    $quantity = (int) $data['quantity'];

    // Ensure quantity is positive
    if ($quantity <= 0) {
      return $this->fail('Quantity must be greater than zero', 400);
    }

    // Check if item exists and belongs to the user
    $exists = $this->model->getItem($itemId);
    if (!$exists || (int) $exists['user_id'] !== $userId) {
      return $this->fail('Cart item not found', 404);
    }

    // Check product stock
    $product = $this->productsModel->getById((int) $exists['product_id']);
    if (!$product) {
      return $this->fail('Product not found', 404);
    }

    if ($quantity > (int) $product['stock']) {
      return $this->fail('Not enough stock available', 400);
    }

    // Update item
    $this->model->updateItem($itemId, $quantity);

    // Return updated item
    return $this->success('Cart item updated', 200, $this->model->getItem($itemId));
  }


  // REMOVE ITEM
  public function removeItem(int $userId, int $itemId): array
  {
    $exists = $this->model->getItem($itemId);

    if (!$exists || (int) $exists['user_id'] !== $userId) {
      return $this->fail('Cart item not found', 404);
    }

    $this->model->removeItem($itemId);
    return $this->success('Item removed from cart', 200);
  }

  // CLEAR CART
  public function clearCart(int $userId): array
  {
    $this->model->clearCart($userId);
    return $this->success('Cart cleared', 200);
  }

  // HELPERS
  private function success(string $message, int $code, ?array $data = null): array
  {
    return ['success' => true, 'message' => $message, 'code' => $code, 'data' => $data];
  }

  private function fail(string $message, int $code, array $errors = []): array
  {
    return ['success' => false, 'message' => $message, 'code' => $code, 'data' => !empty($errors) ? $errors : null];
  }
}
