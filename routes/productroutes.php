<?php

use App\Controllers\ProductsController;

$router->get('/api/products/flash-sales', [ProductsController::class, 'flashSales']);
$router->get('/api/products/list', [ProductsController::class, 'listProducts']);
$router->get('/api/products', [ProductsController::class, 'index']);
$router->get('/api/products/slug/{slug}', [ProductsController::class, 'getBySlug']);
$router->get('/api/products/id/{id}', [ProductsController::class, 'getById']);
$router->post('/api/products', [ProductsController::class, 'create']);
$router->put('/api/products/{id}', [ProductsController::class, 'update']);
$router->delete('/api/products/{id}', [ProductsController::class, 'delete']);