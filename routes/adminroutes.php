<?php

use App\Controllers\AdminController;

// Admin Authentication
$router->post('/api/admin/login', [AdminController::class, 'login']);

// Dashboard (Protected)
$router->get('/api/admin/dashboard', [AdminController::class, 'getDashboard']);

// Orders Management (Protected)
$router->get('/api/admin/orders', [AdminController::class, 'getOrders']);
$router->get('/api/admin/orders/{id}', [AdminController::class, 'getOrderDetails']);
$router->put('/api/admin/orders/{id}/status', [AdminController::class, 'updateOrderStatus']);

// Users Management (Protected)
$router->get('/api/admin/users', [AdminController::class, 'getUsers']);
$router->post('/api/admin/users/{id}/ban', [AdminController::class, 'banUser']);
$router->post('/api/admin/users/{id}/unban', [AdminController::class, 'unbanUser']);

// Products Management (Protected)
$router->get('/api/admin/products', [AdminController::class, 'getProducts']);

// Activity Logs (Protected)
$router->get('/api/admin/logs', [AdminController::class, 'getActivityLogs']);