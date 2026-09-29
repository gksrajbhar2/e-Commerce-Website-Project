<?php
require_once __DIR__ . '/../includes/bootstrap.php';
header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$productId = (int) ($_POST['product_id'] ?? 0);
$quantity  = max(1, (int) ($_POST['quantity'] ?? 1));

$product = $productId ? get_product($productId) : null;

if (!$product) {
    http_response_code(404);
    echo json_encode(['success' => false, 'message' => 'Product not found']);
    exit;
}

$currentInCart = (int) ($_SESSION['cart'][$productId] ?? 0);
if ($currentInCart + $quantity > $product['stock']) {
    echo json_encode([
        'success'    => false,
        'message'    => 'Only ' . $product['stock'] . ' left in stock',
        'cart_count' => get_cart_count(),
    ]);
    exit;
}

add_to_cart($productId, $quantity);

echo json_encode([
    'success'    => true,
    'message'    => 'Added to cart',
    'cart_count' => get_cart_count(),
]);
