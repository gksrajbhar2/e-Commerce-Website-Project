<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$id = (int) ($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM orders WHERE id = :id');
$stmt->execute(['id' => $id]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'Order not found.');
    redirect('admin/orders.php');
}

$validStatuses = ['pending', 'processing', 'shipped', 'completed', 'cancelled'];
$isEsewa       = $order['payment_method'] === 'esewa';
$isCod         = $order['payment_method'] === 'cod';
$esewaUnpaid   = $isEsewa && $order['payment_status'] !== 'paid';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newStatus = $_POST['order_status'] ?? $order['order_status'];

    if (!in_array($newStatus, $validStatuses, true)) {
        set_flash('error', 'Invalid order status.');
        redirect('admin/order_detail.php?id=' . $id);
    }

    // An eSewa order can't move forward until eSewa has confirmed the payment.
    if ($esewaUnpaid && in_array($newStatus, ['processing', 'shipped', 'completed'], true)) {
        set_flash('error', 'This eSewa order has not been paid yet, so it can\'t be marked "' . $newStatus . '". Keep it pending or cancel it.');
        redirect('admin/order_detail.php?id=' . $id);
    }

    // Cash on delivery: the money is collected when the order is completed,
    // so payment status follows the order status (and reverts if that's undone).
    $paymentStatus = $order['payment_status'];
    if ($isCod) {
        $paymentStatus = $newStatus === 'completed' ? 'paid' : 'pending';
    }

    db()->prepare('UPDATE orders SET order_status = :status, payment_status = :pay WHERE id = :id')
        ->execute(['status' => $newStatus, 'pay' => $paymentStatus, 'id' => $id]);

    $msg = 'Order status updated to "' . $newStatus . '".';
    if ($isCod && $paymentStatus !== $order['payment_status']) {
        $msg .= ' Payment marked as ' . $paymentStatus . '.';
    }
    set_flash('success', $msg);
    redirect('admin/order_detail.php?id=' . $id);
}

$itemStmt = db()->prepare('SELECT * FROM order_items WHERE order_id = :id');
$itemStmt->execute(['id' => $id]);
$items = $itemStmt->fetchAll();

$paymentStmt = db()->prepare('SELECT * FROM payments WHERE order_id = :id ORDER BY created_at DESC');
$paymentStmt->execute(['id' => $id]);
$payments = $paymentStmt->fetchAll();

$pageTitle = 'Order #' . $id;
$activeNav = 'orders';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-card">
  <div class="card-head">
    <h2>Order #<?= (int) $order['id'] ?></h2>
    <div class="badge-pair">
      <span>Payment <span class="status-badge status-<?= h($order['payment_status']) ?>"><?= h($order['payment_status']) ?></span></span>
      <span>Order <span class="status-badge status-<?= h($order['order_status']) ?>"><?= h($order['order_status']) ?></span></span>
    </div>
  </div>
  <div class="card-body">
    <div class="form-grid-2">
      <div>
        <h3 style="margin-top:0;">Customer</h3>
        <p><?= h($order['full_name']) ?><br>
           <?= h($order['email']) ?><br>
           <?= h($order['phone']) ?></p>
        <h3>Shipping address</h3>
        <p><?= h($order['address']) ?>, <?= h($order['city']) ?></p>
      </div>
      <div>
        <h3 style="margin-top:0;">Update order status</h3>
        <form method="post">
          <div class="form-group">
            <select name="order_status">
              <?php foreach (['pending', 'processing', 'shipped', 'completed', 'cancelled'] as $s): ?>
                <?php $locked = $esewaUnpaid && in_array($s, ['processing', 'shipped', 'completed'], true); ?>
                <option value="<?= $s ?>" <?= $order['order_status'] === $s ? 'selected' : '' ?> <?= $locked ? 'disabled' : '' ?>><?= ucfirst($s) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <button type="submit" class="btn btn-primary btn-sm">Update status</button>
        </form>
        <?php if ($esewaUnpaid): ?>
          <p class="form-note">Waiting for eSewa payment — this order can't move past “pending” until it's paid.</p>
        <?php elseif ($isCod): ?>
          <p class="form-note">Cash on delivery: completing the order marks the payment as paid.</p>
        <?php endif; ?>

        <h3>Payment</h3>
        <p>Method: <strong><?= h(strtoupper($order['payment_method'])) ?></strong><br>
           Status: <span class="status-badge status-<?= h($order['payment_status']) ?>"><?= h($order['payment_status']) ?></span><br>
           Transaction UUID: <code><?= h($order['transaction_uuid']) ?></code></p>
      </div>
    </div>
  </div>
</div>

<div class="admin-card">
  <div class="card-head"><h2>Items</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="admin-table">
      <thead><tr><th>Product</th><th>Price</th><th>Qty</th><th>Subtotal</th></tr></thead>
      <tbody>
        <?php foreach ($items as $item): ?>
          <tr>
            <td><?= h($item['product_name']) ?></td>
            <td><?= money((float) $item['price']) ?></td>
            <td><?= (int) $item['quantity'] ?></td>
            <td><?= money((float) $item['subtotal']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
    <div style="padding:16px 20px;text-align:right;">
      <div>Subtotal: <?= money((float) $order['subtotal']) ?></div>
      <div>Delivery: <?= money((float) $order['delivery_charge']) ?></div>
      <div style="font-weight:700;font-size:1.05rem;margin-top:4px;">Total: <?= money((float) $order['total_amount']) ?></div>
    </div>
  </div>
</div>

<?php if ($payments): ?>
<div class="admin-card">
  <div class="card-head"><h2>Payment log</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="admin-table">
      <thead><tr><th>Date</th><th>Status</th><th>Transaction code</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr>
            <td><?= date('M j, Y g:i A', strtotime($p['created_at'])) ?></td>
            <td><?= h($p['status']) ?></td>
            <td><?= h($p['transaction_code'] ?? '—') ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
