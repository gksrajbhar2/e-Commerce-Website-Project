<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user = current_user();
$stmt = db()->prepare('SELECT * FROM orders WHERE user_id = :id ORDER BY created_at DESC');
$stmt->execute(['id' => $user['id']]);
$orders = $stmt->fetchAll();

$pageTitle = 'My Orders';
include __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">My Orders</h1>

<?php if (empty($orders)): ?>
  <div class="empty-state">
    <h3>No orders yet</h3>
    <p>Once you place an order, it'll show up here.</p>
    <a href="<?= base_path('index.php') ?>" class="btn btn-primary">Start shopping</a>
  </div>
<?php else: ?>
  <div class="table-wrap" style="margin-bottom:64px;">
  <table class="cart-table">
    <thead>
      <tr>
        <th>Order</th>
        <th>Date</th>
        <th>Total</th>
        <th>Payment</th>
        <th>Status</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($orders as $order): ?>
        <tr>
          <td>#<?= (int) $order['id'] ?></td>
          <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
          <td><?= money((float) $order['total_amount']) ?></td>
          <td><?= h(strtoupper($order['payment_method'])) ?> ·
              <span class="status-badge status-<?= h($order['payment_status']) ?>"><?= h($order['payment_status']) ?></span>
          </td>
          <td><span class="status-badge status-<?= h($order['order_status']) ?>"><?= h($order['order_status']) ?></span></td>
          <td><a href="<?= base_path('order.php?id=' . (int) $order['id']) ?>" class="btn btn-outline btn-sm">Details</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
  </div>
<?php endif; ?>

<?php include __DIR__ . '/includes/footer.php'; ?>
