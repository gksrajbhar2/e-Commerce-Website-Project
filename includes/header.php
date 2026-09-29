<?php
/**
 * Shared storefront header. Expects bootstrap.php to already be loaded.
 * Optional $pageTitle variable can be set before including this file.
 */
$pageTitle = $pageTitle ?? 'Shop';
$user = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= h($pageTitle) ?> · CircuitHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= base_path('assets/css/style.css') ?>">
</head>
<body>

<header class="site-header">
  <div class="container nav" id="mainNav">
    <a href="<?= base_path('index.php') ?>" class="logo">Circuit<span>Hub</span></a>

    <div class="nav-search">
      <form action="<?= base_path('index.php') ?>" method="get">
        <input type="text" name="search" placeholder="Search electronics…" value="<?= h($_GET['search'] ?? '') ?>">
        <button type="submit" aria-label="Search">🔍</button>
      </form>
    </div>

    <ul class="nav-links">
      <li><a href="<?= base_path('index.php') ?>">Shop</a></li>
      <?php if ($user): ?>
        <li><a href="<?= base_path('orders.php') ?>">My Orders</a></li>
        <li><a href="<?= base_path('profile.php') ?>">Hi, <?= h(explode(' ', $user['full_name'])[0]) ?></a></li>
        <li><a href="<?= base_path('auth/logout.php') ?>">Logout</a></li>
      <?php else: ?>
        <li><a href="<?= base_path('auth/login.php') ?>">Login</a></li>
        <li><a href="<?= base_path('auth/register.php') ?>">Register</a></li>
      <?php endif; ?>
    </ul>

    <div class="nav-icons">
      <a href="<?= base_path('cart.php') ?>" class="cart-link">
        🛒 Cart <span class="cart-badge cart-count"><?= get_cart_count() ?></span>
      </a>
      <button class="mobile-nav-toggle" aria-label="Toggle menu" onclick="document.getElementById('mainNav').classList.toggle('open')">☰</button>
    </div>
  </div>
</header>

<main class="container">
  <?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= h($flash['type']) ?>" data-autohide><?= h($flash['message']) ?></div>
  <?php endforeach; ?>
