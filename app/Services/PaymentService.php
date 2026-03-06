<?php

namespace App\Services;

use App\Config\Database;
use App\Models\OrderModel;
use App\Models\OrderItemModel;
use App\Models\OrderHistoryModel;
use App\Models\CartModel;
use App\Models\ProductsModel;
use App\Models\PaymentModel;
use App\Helpers\Validator;

class PaymentService
{
  private OrderModel $orders;
  private OrderItemModel $items;
  private OrderHistoryModel $history;
  private CartModel $cart;
  private ProductsModel $products;
  private PaymentModel $payments;
  private array $config;

  public function __construct(array $config)
  {
    $this->config = $config;

    $db = (new Database($config))->connect();

    $this->orders = new OrderModel($db);
    $this->items = new OrderItemModel($db);
    $this->history = new OrderHistoryModel($db);
    $this->cart = new CartModel($db);
    $this->products = new ProductsModel($db);
    $this->payments = new PaymentModel($db);
  }

  /*--------------------------------------------------------------
   | CASH ON DELIVERY
   --------------------------------------------------------------*/
  public function cashOnDelivery(int $userId, array $data): array
  {
    $errors = Validator::required($data, ['address', 'city', 'state', 'country']);
    if (!empty($errors)) {
      return $this->fail('Shipping address required', 400, $errors);
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
      'payment_method' => 'cash_on_delivery',
      'notes' => $data['notes'] ?? null
    ]);

    foreach ($cartItems as $item) {
      $this->products->reduceStock($item['product_id'], $item['quantity']);
      $this->items->addItem(
        $orderId,
        $item['product_id'],
        $item['name'],
        $item['quantity'],
        (float) $item['price']
      );
    }

    $this->payments->create([
      'order_id' => $orderId,
      'user_id' => $userId,
      'amount' => $total,
      'method' => 'cash_on_delivery',
      'status' => 'pending'
    ]);

    $this->history->addStatus($orderId, 'pending');
    $this->cart->clearCart($userId);

    return $this->success('Order placed successfully', 201, [
      'order_id' => $orderId,
      'total' => $total,
      'method' => 'cash_on_delivery'
    ]);
  }

  /*--------------------------------------------------------------
   | INITIATE PAYSTACK PAYMENT
   --------------------------------------------------------------*/
  public function initiatePayment(int $userId, array $data): array
  {
    $errors = Validator::required($data, ['address', 'city', 'state', 'country', 'email']);
    if (!empty($errors)) {
      return $this->fail('Missing required fields', 400, $errors);
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

    $reference = 'ECM-' . strtoupper(uniqid());

    $secret = $this->config['PAYSTACK_SECRET_KEY'];
    $callback = ($this->config['APP_URL'] ?? '') . '/api/checkout/verify/' . $reference;
    $response = $this->callPaystack(
      'https://api.paystack.co/transaction/initialize',
      [
        'email' => $data['email'],
        'amount' => (int) ($total * 100),
        'reference' => $reference,
        'currency' => 'NGN',
        'callback_url' => $callback,
        'channels' => ['card', 'bank', 'ussd', 'bank_transfer'],
        'metadata' => [
          'user_id' => $userId,
          'order_ref' => $reference
        ]
      ],
      $secret
    );

    if (!$response || !$response['status']) {
      return $this->fail('Could not initiate payment', 500);
    }

    $this->payments->createPending([
      'user_id' => $userId,
      'reference' => $reference,
      'amount' => $total,
      'method' => 'card',
      'address' => $data['address'],
      'city' => $data['city'],
      'state' => $data['state'],
      'country' => $data['country'],
      'notes' => $data['notes'] ?? null
    ]);

    return $this->success('Payment initiated', 200, [
      'payment_url' => $response['data']['authorization_url'],
      'reference' => $reference,
      'amount' => $total
    ]);
  }

  /*--------------------------------------------------------------
   | VERIFY PAYSTACK PAYMENT
   --------------------------------------------------------------*/
  public function verifyPayment(?int $userId, string $reference): array
  {
    $secret = $this->config['PAYSTACK_SECRET_KEY'];

    $response = $this->callPaystackGet(
      "https://api.paystack.co/transaction/verify/{$reference}",
      $secret
    );

    if (!$response || !$response['status']) {
      return $this->fail('Payment verification failed', 400);
    }

    $data = $response['data'];

    if ($data['status'] !== 'success') {
      return $this->fail('Payment was not successful', 400);
    }

    // Get pending payment to find userId if not provided
    $pending = $this->payments->getPendingByReference($reference);
    if (!$pending) {
      return $this->fail('Payment record not found', 404);
    }

    // Use userId from payment record if not provided
    if (!$userId) {
      $userId = (int) $pending['user_id'];
    }

    // Already processed
    if ($pending['status'] === 'success' && $pending['order_id']) {
      return $this->success('Payment verified', 200, [
        'order_id' => $pending['order_id'],
        'total' => $pending['amount'],
        'paid_at' => $pending['paid_at']
      ]);
    }

    return $this->processSuccessfulPayment($userId, $reference, $data);
  }
  /*--------------------------------------------------------------
   | WEBHOOK
   --------------------------------------------------------------*/
  public function handleWebhook(): array
  {
    try {
      $secret = $this->config['PAYSTACK_SECRET_KEY'] ?? '';
      $payload = file_get_contents('php://input');

      if (empty($payload)) {
        error_log("Webhook: Empty payload received");
        return $this->success('No payload', 200);
      }

      $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
      $expected = hash_hmac('sha512', $payload, $secret);

      if ($signature !== $expected) {
        error_log("Webhook: Invalid signature");
        return $this->fail('Invalid webhook signature', 401);
      }

      $event = json_decode($payload, true);

      if (!$event || !isset($event['event'])) {
        error_log("Webhook: Invalid JSON payload");
        return $this->success('Invalid payload', 200);
      }

      error_log("Webhook: Event received - " . $event['event']);

      if ($event['event'] !== 'charge.success') {
        return $this->success('Event ignored', 200);
      }

      $data = $event['data'];
      $reference = $data['reference'] ?? '';

      if (!$reference) {
        error_log("Webhook: No reference in payload");
        return $this->success('No reference', 200);
      }

      $pending = $this->payments->getPendingByReference($reference);

      if (!$pending) {
        error_log("Webhook: Reference not found - " . $reference);
        return $this->success('Reference not found', 200);
      }

      if ($pending['status'] === 'success') {
        error_log("Webhook: Already processed - " . $reference);
        return $this->success('Already processed', 200);
      }

      $result = $this->processSuccessfulPayment(
        (int) $pending['user_id'],
        $reference,
        $data
      );

      error_log("Webhook: Processed - order created for " . $reference);

      return $result;

    } catch (\Throwable $e) {
      error_log("Webhook error: " . $e->getMessage());
      return $this->success('Webhook received', 200); // Always return 200 to Paystack
    }
  }
  /*--------------------------------------------------------------
   | PROCESS SUCCESSFUL PAYMENT (MISSING METHOD ADDED)
   --------------------------------------------------------------*/
  private function processSuccessfulPayment(int $userId, string $reference, array $paystackData): array
  {
    $pending = $this->payments->getPendingByReference($reference);
    if (!$pending) {
      return $this->fail('Payment record not found', 404);
    }

    $cartItems = $this->cart->getCart($userId);
    if (empty($cartItems)) {
      return $this->fail('Cart is empty', 400);
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
      'address' => $pending['address'],
      'city' => $pending['city'],
      'state' => $pending['state'],
      'country' => $pending['country'],
      'payment_method' => $paystackData['channel'] ?? 'card',
      'notes' => $pending['notes'] ?? null
    ]);

    foreach ($cartItems as $item) {
      $this->products->reduceStock($item['product_id'], $item['quantity']);
      $this->items->addItem(
        $orderId,
        $item['product_id'],
        $item['name'],
        $item['quantity'],
        (float) $item['price']
      );
    }

    $this->payments->markPaid($reference, $orderId, $paystackData);
    $this->orders->updatePaymentStatus($orderId, 'paid');
    $this->history->addStatus($orderId, 'processing');
    $this->cart->clearCart($userId);

    return $this->success('Payment successful', 200, [
      'order_id' => $orderId,
      'total' => $total,
      'method' => $paystackData['channel'] ?? 'card',
      'paid_at' => date('Y-m-d H:i:s')
    ]);
  }

  /*--------------------------------------------------------------
   | PAYSTACK HELPERS
   --------------------------------------------------------------*/
  private function callPaystack(string $url, array $data, string $secret): ?array
  {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . $secret,
      'Content-Type: application/json',
      'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
  }

  private function callPaystackGet(string $url, string $secret): ?array
  {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      'Authorization: Bearer ' . $secret,
      'Accept: application/json'
    ]);

    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
  }

  /*--------------------------------------------------------------
   | HELPERS
   --------------------------------------------------------------*/
  private function success(string $message, int $code, ?array $data = null): array
  {
    return ['success' => true, 'message' => $message, 'code' => $code, 'data' => $data];
  }

  private function fail(string $message, int $code, array $errors = []): array
  {
    return ['success' => false, 'message' => $message, 'code' => $code, 'data' => !empty($errors) ? $errors : null];
  }
}
