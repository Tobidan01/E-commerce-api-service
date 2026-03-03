<?php

use App\Controllers\OrderController;

$router->post('/api/orders', [OrderController::class, 'createOrder']);
$router->get('/api/orders', [OrderController::class, 'getMyOrders']);
$router->get('/api/orders/{id}', [OrderController::class, 'getOrderById']);
$router->delete('/api/orders/{id}', [OrderController::class, 'cancelOrder']);

// ADMIN
$router->get('/api/admin/orders', [OrderController::class, 'getAllOrders']);
$router->put('/api/admin/orders/{id}/status', [OrderController::class, 'updateStatus']);
$router->get('/api/admin/orders/{id}/timeline', [OrderController::class, 'getOrderTimeline']);
$router->get('/api/admin/dashboard', [OrderController::class, 'dashboard']);