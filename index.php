<?php
require_once __DIR__ . '/includes/bootstrap.php';

$categorySlug = $_GET['category'] ?? null;
$search       = trim($_GET['search'] ?? '');
$products     = get_products($categorySlug ?: null, $search ?: null);
$categories   = get_categories_with_products();

$pageTitle = 'Shop';
include __DIR__ . '/includes/header.php';
?>

<?php if (!$categorySlug && !$search): ?>
<section class="hero">
  <div class="container">
    <div>
      <span class="hero-badge">Genuine devices · Secure eSewa checkout</span>
      <h1>The latest tech, delivered fast.</h1>
      <p>Smart devices, computer accessories, audio, storage and power gear — all genuine, all in stock, with a checkout you already trust.</p>
      <div class="hero-actions">
        <a href="#catalog" class="btn btn-accent">Browse products</a>
        <a href="<?= base_path('index.php?category=smart-devices') ?>" class="btn btn-outline">Shop smart devices</a>
      </div>
    </div>
    <div class="hero-art" aria-hidden="true"></div>
  </div>
</section>
<?php endif; ?>

<div id="catalog"></div>

<div class="section-head">
  <h2>
    <?php if ($search): ?>
      Results for "<?= h($search) ?>"
    <?php elseif ($categorySlug): ?>
      <?= h(ucwords(str_replace('-', ' ', $categorySlug))) ?>
    <?php else: ?>
      All products
    <?php endif; ?>
  </h2>
  <span style="color:var(--color-ink-soft);font-size:0.9rem;"><?= count($products) ?> item<?= count($products) === 1 ? '' : 's' ?></span>
</div>

<div class="category-row">
  <a href="<?= base_path('index.php') ?>" class="chip <?= !$categorySlug ? 'active' : '' ?>">All</a>
  <?php foreach ($categories as $cat): ?>
    <a href="<?= base_path('index.php?category=' . urlencode($cat['slug'])) ?>"
       class="chip <?= $categorySlug === $cat['slug'] ? 'active' : '' ?>"><?= h($cat['name']) ?></a>
  <?php endforeach; ?>
</div>

<?php if (empty($products)): ?>
  <div class="empty-state">
    <h3>No products found</h3>
    <p>Try a different search term or browse another category.</p>
    <a href="<?= base_path('index.php') ?>" class="btn btn-primary">View all products</a>
  </div>
<?php else: ?>
  <div class="product-grid">
    <?php foreach ($products as $product): ?>
      <div class="product-card">
        <a href="<?= base_path('product.php?id=' . $product['id']) ?>" class="product-thumb">
          <img src="<?= h(product_image_url($product['image'])) ?>" alt="<?= h($product['name']) ?>" loading="lazy">
        </a>
        <div class="product-body">
          <?php if ($product['category_name']): ?>
            <span class="product-category"><?= h($product['category_name']) ?></span>
          <?php endif; ?>
          <div class="product-name"><a href="<?= base_path('product.php?id=' . $product['id']) ?>"><?= h($product['name']) ?></a></div>
          <span class="product-price"><?= money($product['price']) ?></span>
          <span class="product-stock <?= $product['stock'] <= 5 ? 'low' : '' ?>">
            <?= $product['stock'] > 0 ? $product['stock'] . ' in stock' : 'Out of stock' ?>
          </span>
        </div>
        <div class="product-actions">
          <?php if ($product['stock'] > 0): ?>
            <form class="ajax-add-to-cart" method="post">
              <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
              <input type="hidden" name="quantity" value="1">
              <button type="submit" class="btn btn-primary btn-block btn-sm">Add to cart</button>
            </form>
          <?php else: ?>
            <button class="btn btn-outline btn-block btn-sm" disabled>Out of stock</button>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
