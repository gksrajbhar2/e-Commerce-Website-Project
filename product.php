<?php
require_once __DIR__ . '/includes/bootstrap.php';

$id = (int) ($_GET['id'] ?? 0);
$product = $id ? get_product($id) : null;

if (!$product) {
    set_flash('error', 'That product could not be found.');
    redirect('index.php');
}

// Pull category name for the breadcrumb.
$category = null;
if ($product['category_id']) {
    $stmt = db()->prepare('SELECT * FROM categories WHERE id = :id');
    $stmt->execute(['id' => $product['category_id']]);
    $category = $stmt->fetch();
}

$pageTitle = $product['name'];
include __DIR__ . '/includes/header.php';
?>

<div class="breadcrumb">
  <a href="<?= base_path('index.php') ?>">Shop</a>
  <?php if ($category): ?>
    › <a href="<?= base_path('index.php?category=' . urlencode($category['slug'])) ?>"><?= h($category['name']) ?></a>
  <?php endif; ?>
  › <?= h($product['name']) ?>
</div>

<div class="product-detail">
  <div class="product-detail-gallery">
    <img src="<?= h(product_image_url($product['image'])) ?>" alt="<?= h($product['name']) ?>">
  </div>

  <div class="product-detail-info">
    <?php if ($category): ?><span class="product-category"><?= h($category['name']) ?></span><?php endif; ?>
    <h1><?= h($product['name']) ?></h1>
    <p><?= nl2br(h($product['description'])) ?></p>
    <div class="product-price"><?= money($product['price']) ?></div>

    <p class="product-stock <?= $product['stock'] <= 5 ? 'low' : '' ?>">
      <?= $product['stock'] > 0 ? $product['stock'] . ' in stock' : 'Out of stock' ?>
    </p>

    <?php if ($product['stock'] > 0): ?>
      <form class="ajax-add-to-cart" method="post">
        <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
        <div class="add-to-cart-row">
          <div class="qty-control">
            <button type="button" data-step="-1" aria-label="Decrease quantity">−</button>
            <input type="number" name="quantity" value="1" min="1" max="<?= (int) $product['stock'] ?>">
            <button type="button" data-step="1" aria-label="Increase quantity">+</button>
          </div>
          <button type="submit" class="btn btn-primary">Add to cart</button>
        </div>
      </form>
      <a href="<?= base_path('cart.php') ?>" class="btn btn-outline btn-block">Go to cart</a>
    <?php else: ?>
      <button class="btn btn-outline" disabled>Out of stock</button>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
