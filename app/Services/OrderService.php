<?php

namespace App\Services;

use App\Config\Database;
use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\CartModel;
use App\Models\OrderHistoryModel;
use App\Models\ProductsModel;
use App\Helpers\Validator;
use App\Helpers\JwtAuth;

class OrderService
{
  private OrderModel $orders;
  private OrderItemModel $items;
  private CartModel $cart;
  private OrderHistoryModel $history;
  private ProductsModel $products;

  public function __construct(array $config)
  {
    $db = (new Database($config))->connect();

    $this->orders = new OrderModel($db);
    $this->items = new OrderItemModel($db);
    $this->cart = new CartModel($db);
    $this->history = new OrderHistoryModel($db);
    $this->products = new ProductsModel($db);
  }

  public function createOrder(int $userId, array $data): array
  {
    $errors = Validator::required($data, ['address', 'city', 'state', 'country']);
    if (!empty($errors)) {
      return $this->fail('Shipping address is required', 400, $errors);
    }

    $cartItems = $this->cart->getCart($userId);
    if (empty($cartItems)) {
      return $this->fail('Your cart is empty', 400);
    }

    $subtotal = 0;
    foreach ($cartItems as $item) {
      $subtotal += $item['price'] * $item['quantity'];
    }

    $subtotal = round($subtotal, 2);
    $shipping = $subtotal >= 100 ? 0 : 10;
    $total = round($subtotal + $shipping, 2);

    $orderId = $this->orders->createOrder($userId, [
      'subtotal' => $subtotal,
      'shipping' => $shipping,
      'total' => $total,
      'address' => $data['address'],
      'city' => $data['city'],
      'state' => $data['state'],
      'country' => $data['country'],
      'payment_method' => $data['payment_method'] ?? 'cash_on_delivery',
      'notes' => $data['notes'] ?? null
    ]);

    foreach ($cartItems as $item) {
      $success = $this->products->reduceStock($item['product_id'], $item['quantity']);

      if (!$success) {
        return $this->fail("Not enough stock for: {$item['name']}", 400);
      }

      $this->items->addItem(
        $orderId,
        $item['product_id'],
        $item['name'],
        $item['quantity'],
        (float) $item['price']
      );
    }

    $this->history->addStatus($orderId, 'pending');
    $this->cart->clearCart($userId);

    return $this->success('Order placed successfully', 201, $this->orders->getOrderWithItems($orderId));
  }

  public function getMyOrders(int $userId): array
  {
    return $this->orders->getOrdersByUser($userId);
  }

  public function getOrderById(int $userId, int $orderId): ?array
  {
    $order = $this->orders->getOrderWithItems($orderId);

    if (!$order || (int) $order['user_id'] !== $userId) {
      return null;
    }

    return $order;
  }

  public function cancelOrder(int $userId, int $orderId): array
  {
    $order = $this->orders->getOrder($orderId);

    if (!$order || (int) $order['user_id'] !== $userId) {
      return $this->fail('Order not found', 404);
    }

    if ($order['status'] !== 'pending') {
      return $this->fail('Only pending orders can be cancelled', 400);
    }

    $this->orders->updateStatus($orderId, 'cancelled');
    $this->history->addStatus($orderId, 'cancelled');

    return $this->success('Order cancelled', 200);
  }

  public function getAllOrders(): array
  {
    return $this->orders->getAllOrders();
  }

  public function updateStatus(int $orderId, ?string $status): array
  {
    JwtAuth::requireAdmin(); // 🔥 CRITICAL
    if (!$status) {
      return $this->fail('Status is required', 400);
    }

    $allowed = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($status, $allowed)) {
      return $this->fail('Invalid status', 400);
    }

    $order = $this->orders->getOrder($orderId);
    if (!$order) {
      return $this->fail('Order not found', 404);
    }

    $this->orders->updateStatus($orderId, $status);
    $this->history->addStatus($orderId, $status);

    return $this->success('Order status updated', 200);
  }

  public function getOrderTimeline(int $orderId): array
  {
    return $this->history->getHistory($orderId);
  }

  public function getDashboardStats(): array
  {
    return [
      'total_orders' => $this->orders->countOrders(),
      'total_revenue' => $this->orders->sumRevenue(),
      'pending_orders' => $this->orders->countByStatus('pending'),
      'shipped_orders' => $this->orders->countByStatus('shipped'),
      'delivered_orders' => $this->orders->countByStatus('delivered'),
      'recent_orders' => $this->orders->getRecentOrders()
    ];
  }

  private function success(string $message, int $code, ?array $data = null): array
  {
    return ['success' => true, 'message' => $message, 'code' => $code, 'data' => $data];
  }

  private function fail(string $message, int $code, array $errors = []): array
  {
    return ['success' => false, 'message' => $message, 'code' => $code, 'data' => !empty($errors) ? $errors : null];
  }
}
