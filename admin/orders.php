<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$statusFilter = $_GET['status'] ?? '';
$sql = 'SELECT * FROM orders';
$params = [];
if ($statusFilter) {
    $sql .= ' WHERE order_status = :status';
    $params['status'] = $statusFilter;
}
$sql .= ' ORDER BY created_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

$counts = ['' => 0];
foreach (db()->query('SELECT order_status, COUNT(*) AS n FROM orders GROUP BY order_status')->fetchAll() as $row) {
    $counts[$row['order_status']] = (int) $row['n'];
    $counts[''] += (int) $row['n'];
}

$pageTitle = 'Orders';
$activeNav = 'orders';
include __DIR__ . '/includes/admin_header.php';
?>

<nav class="filter-tabs" aria-label="Filter orders by status">
  <a href="<?= base_path('admin/orders.php') ?>" class="filter-tab <?= !$statusFilter ? 'active' : '' ?>">All <span class="count"><?= $counts[''] ?></span></a>
  <?php foreach (['pending', 'processing', 'shipped', 'completed', 'cancelled'] as $st): ?>
    <a href="<?= base_path('admin/orders.php?status=' . $st) ?>" class="filter-tab <?= $statusFilter === $st ? 'active' : '' ?>"><?= ucfirst($st) ?> <span class="count"><?= $counts[$st] ?? 0 ?></span></a>
  <?php endforeach; ?>
</nav>

<div class="admin-card">
  <div class="card-head"><h2>Orders (<?= count($orders) ?>)</h2></div>
  <div class="card-body" style="padding:0;">
    <table class="admin-table">
      <thead>
        <tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Order status</th><th>Date</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($orders)): ?>
          <tr class="empty-row"><td colspan="7">No orders found.</td></tr>
        <?php else: foreach ($orders as $order): ?>
          <tr>
            <td>#<?= (int) $order['id'] ?></td>
            <td><?= h($order['full_name']) ?><br><span style="color:var(--admin-ink-soft);font-size:0.8rem;"><?= h($order['email']) ?></span></td>
            <td><?= money((float) $order['total_amount']) ?></td>
            <td><?= h(strtoupper($order['payment_method'])) ?> ·
                <span class="status-badge status-<?= h($order['payment_status']) ?>"><?= h($order['payment_status']) ?></span></td>
            <td><span class="status-badge status-<?= h($order['order_status']) ?>"><?= h($order['order_status']) ?></span></td>
            <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
            <td><a href="<?= base_path('admin/order_detail.php?id=' . $order['id']) ?>" class="btn btn-outline btn-sm">View</a></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
