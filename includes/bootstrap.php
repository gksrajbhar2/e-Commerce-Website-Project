<?php
/**
 * Bootstrap — included at the top of every page. Starts the session,
 * loads configuration, and pulls in the shared helper/auth functions.
 */

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Basic security headers.
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');

date_default_timezone_set('Asia/Kathmandu');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/esewa.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/auth.php';

// Make the session cart array always available.
if (!isset($_SESSION['cart']) || !is_array($_SESSION['cart'])) {
    $_SESSION['cart'] = []; // [product_id => quantity]
}

// Admins manage the store — they don't shop in it. A logged-in admin who
// lands on any storefront page (or tries the cart API) is sent back to the
// admin dashboard. Only /admin/* and the logout script stay reachable.
$requestScript = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
if (is_admin()
    && !str_starts_with($requestScript, '/admin/')
    && $requestScript !== '/auth/logout.php') {
    if (str_starts_with($requestScript, '/api/')) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Admin accounts manage the store and cannot place orders.']);
        exit;
    }
    set_flash('info', 'Admin accounts manage the store — they can\'t shop. Log out to browse as a customer.');
    redirect('admin/dashboard.php');
}
