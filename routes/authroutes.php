<?php

use App\Controllers\AuthController;

$router->post('/api/auth/register', [AuthController::class, 'register']);
$router->post('/api/auth/login', [AuthController::class, 'login']);
$router->post('/api/auth/google', [AuthController::class, 'loginWithGoogle']);
$router->get('/api/auth/me', [AuthController::class, 'me']);