<?php
/**
 * Shared admin header. Expects bootstrap.php already loaded and
 * require_admin() already called by the including page.
 * Optional $pageTitle and $activeNav ('dashboard'|'products'|'orders').
 */
$pageTitle = $pageTitle ?? 'Admin';
$activeNav = $activeNav ?? '';
$admin = current_user();

$icons = [
  'dashboard' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/></svg>',
  'products'  => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M21 8 12 3 3 8v8l9 5 9-5V8Z"/><path d="m3 8 9 5 9-5M12 13v8"/></svg>',
  'orders'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M6 3h12v18l-3-2-3 2-3-2-3 2V3Z"/><path d="M9 8h6M9 12h6"/></svg>',
  'esewa'     => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M12 3 4 6v6c0 4.5 3.2 7.7 8 9 4.8-1.3 8-4.5 8-9V6l-8-3Z"/><path d="m9 12 2 2 4-4"/></svg>',
  'logout'    => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><path d="m16 17 5-5-5-5M21 12H9"/></svg>',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle) ?> · Admin · CircuitHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= base_path('assets/css/admin.css') ?>">
</head>
<body class="admin">

<aside class="admin-sidebar">
  <div class="brand">Circuit<span>Hub</span><small>Admin</small></div>
  <a href="<?= base_path('admin/dashboard.php') ?>" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>"><?= $icons['dashboard'] ?>Dashboard</a>
  <a href="<?= base_path('admin/products.php') ?>" class="<?= $activeNav === 'products' ? 'active' : '' ?>"><?= $icons['products'] ?>Products</a>
  <a href="<?= base_path('admin/orders.php') ?>" class="<?= $activeNav === 'orders' ? 'active' : '' ?>"><?= $icons['orders'] ?>Orders</a>
  <a href="<?= base_path('admin/esewa_check.php') ?>" class="<?= $activeNav === 'esewa' ? 'active' : '' ?>"><?= $icons['esewa'] ?>eSewa check</a>
  <hr class="divider">
  <a href="<?= base_path('admin/logout.php') ?>"><?= $icons['logout'] ?>Logout</a>
</aside>

<div class="admin-main">
  <div class="admin-topbar">
    <h1><?= h($pageTitle) ?></h1>
    <span class="admin-user"><?= h($admin['full_name'] ?? '') ?></span>
  </div>
  <div class="admin-content">
    <?php foreach (get_flashes() as $flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endforeach; ?>
