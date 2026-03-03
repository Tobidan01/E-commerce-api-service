<?php

use App\Controllers\CategoryController;

$router->get('/api/categories', [CategoryController::class, 'index']);
$router->post('/api/categories', [CategoryController::class, 'create']);
$router->get('/api/categories/{id}', [CategoryController::class, 'getById']);
$router->put('/api/categories/{id}', [CategoryController::class, 'update']);
$router->delete('/api/categories/{id}', [CategoryController::class, 'delete']);