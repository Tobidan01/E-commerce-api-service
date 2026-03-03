<?php
use App\Controllers\WishlistController;

$router->get('/wishlist', [WishlistController::class, 'index']);
$router->post('/wishlist/{productId}', [WishlistController::class, 'add']);
$router->delete('/wishlist/{productId}', [WishlistController::class, 'remove']);