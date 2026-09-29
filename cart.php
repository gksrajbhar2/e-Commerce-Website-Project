<?php
require_once __DIR__ . '/includes/bootstrap.php';

// Handle quantity update / remove (plain POST — works without JS too).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $productId = (int) ($_POST['product_id'] ?? 0);
    $action    = $_POST['action'] ?? 'update';

    if ($productId) {
        if ($action === 'remove') {
            remove_from_cart($productId);
            set_flash('success', 'Item removed from cart.');
        } else {
            update_cart_quantity($productId, (int) ($_POST['quantity'] ?? 1));
        }
    }
    redirect('cart.php');
}

$items = get_cart_items();
$total = get_cart_total();

$pageTitle = 'Your Cart';
include __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">Your Cart</h1>

<?php if (empty($items)): ?>
  <div class="empty-state">
    <h3>Your cart is empty</h3>
    <p>Add a few things you like — they'll show up here.</p>
    <a href="<?= base_path('index.php') ?>" class="btn btn-primary">Continue shopping</a>
  </div>
<?php else: ?>
  <div class="cart-layout">
    <table class="cart-table">
      <thead>
        <tr>
          <th>Product</th>
          <th>Price</th>
          <th>Quantity</th>
          <th>Subtotal</th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $item): $p = $item['product']; ?>
          <tr>
            <td>
              <div class="cart-product">
                <img src="<?= h(product_image_url($p['image'])) ?>" alt="<?= h($p['name']) ?>">
                <a href="<?= base_path('product.php?id=' . $p['id']) ?>"><?= h($p['name']) ?></a>
              </div>
            </td>
            <td><?= money($p['price']) ?></td>
            <td>
              <form class="cart-qty-form" method="post">
                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                <div class="qty-control">
                  <button type="button" data-step="-1" aria-label="Decrease quantity">−</button>
                  <input type="number" name="quantity" value="<?= $item['quantity'] ?>" min="1" max="<?= (int) $p['stock'] ?>">
                  <button type="button" data-step="1" aria-label="Increase quantity">+</button>
                </div>
              </form>
            </td>
            <td><?= money($item['subtotal']) ?></td>
            <td>
              <form method="post">
                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                <input type="hidden" name="action" value="remove">
                <button type="submit" class="cart-remove">Remove</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>

    <div class="summary-card">
      <h3>Order Summary</h3>
      <div class="summary-row"><span>Subtotal</span><span><?= money($total) ?></span></div>
      <div class="summary-row"><span>Delivery</span><span>Calculated at checkout</span></div>
      <div class="summary-row total"><span>Estimated total</span><span><?= money($total) ?></span></div>
      <a href="<?= base_path('checkout.php') ?>" class="btn btn-primary btn-block" style="margin-top:16px;">Proceed to checkout</a>
      <a href="<?= base_path('index.php') ?>" class="btn btn-outline btn-block" style="margin-top:10px;">Continue shopping</a>
    </div>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
