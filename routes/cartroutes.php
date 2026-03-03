<?php

use App\Controllers\CartController;

$router->get('/api/cart', [CartController::class, 'getCart']);
$router->post('/api/cart/add', [CartController::class, 'addToCart']);
$router->put('/api/cart/{id}', [CartController::class, 'updateItem']);
$router->delete('/api/cart', [CartController::class, 'clearCart']);
$router->delete('/api/cart/{id}', [CartController::class, 'removeItem']);