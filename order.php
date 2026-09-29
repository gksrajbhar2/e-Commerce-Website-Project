<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$id   = (int) ($_GET['id'] ?? 0);

// Customers can only open their own orders.
$stmt = db()->prepare('SELECT * FROM orders WHERE id = :id AND user_id = :uid');
$stmt->execute(['id' => $id, 'uid' => $user['id']]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'That order could not be found.');
    redirect('orders.php');
}

$itemStmt = db()->prepare(
    'SELECT oi.*, p.image FROM order_items oi
     LEFT JOIN products p ON p.id = oi.product_id
     WHERE oi.order_id = :id'
);
$itemStmt->execute(['id' => $id]);
$items = $itemStmt->fetchAll();

// Progress tracker: placed -> processing -> shipped -> completed.
$steps = ['pending' => 'Order placed', 'processing' => 'Processing', 'shipped' => 'Shipped', 'completed' => 'Completed'];
$keys  = array_keys($steps);
$reached = array_search($order['order_status'], $keys, true);
$cancelled = $order['order_status'] === 'cancelled';

$pageTitle = 'Order #' . $id;
include __DIR__ . '/includes/header.php';
?>

<div class="order-head">
  <div>
    <a href="<?= base_path('orders.php') ?>" class="back-link">← All orders</a>
    <h1 class="page-title" style="margin:8px 0 0;">Order #<?= (int) $order['id'] ?></h1>
    <p style="color:var(--color-ink-soft);margin:6px 0 0;">Placed on <?= date('F j, Y \a\t g:i A', strtotime($order['created_at'])) ?></p>
  </div>
  <div class="order-badges">
    <span>Payment <span class="status-badge status-<?= h($order['payment_status']) ?>"><?= h($order['payment_status']) ?></span></span>
    <span>Order <span class="status-badge status-<?= h($order['order_status']) ?>"><?= h($order['order_status']) ?></span></span>
  </div>
</div>

<?php if ($cancelled): ?>
  <div class="alert alert-error">This order was cancelled.</div>
<?php else: ?>
  <ol class="order-tracker" aria-label="Order progress">
    <?php foreach ($steps as $key => $label): $i = array_search($key, $keys, true); ?>
      <li class="<?= $i < $reached ? 'done' : ($i === $reached ? 'current' : '') ?>"><span></span><?= h($label) ?></li>
    <?php endforeach; ?>
  </ol>
<?php endif; ?>

<div class="cart-layout" style="margin-top:28px;">
  <div>
    <div class="table-wrap">
      <table class="cart-table">
        <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <div class="cart-product">
                  <img src="<?= h(product_image_url($item['image'] ?? null)) ?>" alt="">
                  <span><?= h($item['product_name']) ?></span>
                </div>
              </td>
              <td><?= money((float) $item['price']) ?></td>
              <td><?= (int) $item['quantity'] ?></td>
              <td><?= money((float) $item['subtotal']) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <aside>
    <div class="summary-card">
      <h3>Summary</h3>
      <div class="summary-row"><span>Subtotal</span><span><?= money((float) $order['subtotal']) ?></span></div>
      <div class="summary-row"><span>Delivery</span><span><?= (float) $order['delivery_charge'] > 0 ? money((float) $order['delivery_charge']) : 'Free' ?></span></div>
      <div class="summary-row total"><span>Total</span><span><?= money((float) $order['total_amount']) ?></span></div>
      <div class="summary-row"><span>Payment method</span><span><?= $order['payment_method'] === 'cod' ? 'Cash on delivery' : 'eSewa' ?></span></div>
    </div>

    <div class="summary-card" style="margin-top:18px;">
      <h3>Delivery details</h3>
      <p style="color:var(--color-ink);margin:0;line-height:1.7;">
        <?= h($order['full_name']) ?><br>
        <?= h($order['address']) ?>, <?= h($order['city']) ?><br>
        <?= h($order['phone']) ?><br>
        <?= h($order['email']) ?>
      </p>
    </div>
  </aside>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
