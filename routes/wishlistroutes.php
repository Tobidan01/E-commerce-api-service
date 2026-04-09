<?php
use App\Controllers\WishlistController;

$router->get('/wishlist', [WishlistController::class, 'index']);
$router->post('/api/wishlist/{id}', [WishlistController::class, 'add']);
$router->delete('/api/wishlist/{id}', [WishlistController::class, 'remove']);