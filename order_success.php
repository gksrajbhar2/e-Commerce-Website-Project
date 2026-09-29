<?php
require_once __DIR__ . '/includes/bootstrap.php';

$orderId = $_SESSION['last_order_id'] ?? null;
if (!$orderId) {
    set_flash('error', 'No recent order found.');
    redirect('index.php');
}

$stmt = db()->prepare('SELECT * FROM orders WHERE id = :id');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'That order could not be found.');
    redirect('index.php');
}

$itemStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :id');
$itemStmt->execute(['id' => $orderId]);
$items = $itemStmt->fetchAll();

$pageTitle = 'Order Confirmed';
include __DIR__ . '/includes/header.php';
?>

<div class="empty-state" style="padding-bottom:0;">
  <h1 style="color:var(--color-success);">Thank you — your order is confirmed!</h1>
  <p>
    Order <strong>#<?= (int) $order['id'] ?></strong> ·
    <span class="status-badge status-<?= h($order['payment_status']) ?>"><?= h($order['payment_status']) ?></span>
    <?php if ($order['payment_method'] === 'cod'): ?> · Pay on delivery<?php endif; ?>
  </p>
</div>

<div class="form-card" style="max-width:620px;margin:24px auto 64px;">
  <h3 class="mt-0">Order details</h3>
  <?php foreach ($items as $item): ?>
    <div class="summary-row">
      <span><?= h($item['product_name']) ?> × <?= (int) $item['quantity'] ?></span>
      <span><?= money((float) $item['subtotal']) ?></span>
    </div>
  <?php endforeach; ?>
  <div class="summary-row"><span>Subtotal</span><span><?= money((float) $order['subtotal']) ?></span></div>
  <div class="summary-row"><span>Delivery</span><span><?= (float) $order['delivery_charge'] > 0 ? money((float) $order['delivery_charge']) : 'Free' ?></span></div>
  <div class="summary-row total"><span><?= $order['payment_status'] === 'paid' ? 'Total paid' : 'Total to pay' ?></span><span><?= money((float) $order['total_amount']) ?></span></div>

  <h3>Shipping to</h3>
  <p style="color:var(--color-ink);">
    <?= h($order['full_name']) ?><br>
    <?= h($order['address']) ?>, <?= h($order['city']) ?><br>
    <?= h($order['phone']) ?> · <?= h($order['email']) ?>
  </p>

  <a href="<?= base_path('index.php') ?>" class="btn btn-primary btn-block">Continue shopping</a>
  <?php if (is_logged_in() && (int) $order['user_id'] === (int) current_user()['id']): ?>
    <a href="<?= base_path('order.php?id=' . (int) $order['id']) ?>" class="btn btn-outline btn-block" style="margin-top:10px;">View this order in my account</a>
  <?php endif; ?>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
