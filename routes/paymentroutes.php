<?php

use App\Controllers\CheckoutController;

$router->post('/api/checkout/cash', [CheckoutController::class, 'cash']);
$router->post('/api/checkout/initiate', [CheckoutController::class, 'initiate']);
$router->get('/api/checkout/verify/{reference}', [CheckoutController::class, 'verify']);
$router->post('/api/checkout/webhook', [CheckoutController::class, 'webhook']); // ✅ new
