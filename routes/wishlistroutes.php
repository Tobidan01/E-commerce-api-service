<?php
use App\Controllers\WishlistController;

$router->get('/api/wishlist', [WishlistController::class, 'index']);
$router->post('/api/wishlist/{id}', [WishlistController::class, 'add']);
$router->delete('/api/wishlist/{id}', [WishlistController::class, 'remove']);
