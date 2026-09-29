<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$pdo = db();

$totalRevenue = (float) $pdo->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status = 'paid'")->fetchColumn();
$totalOrders  = (int) $pdo->query('SELECT COUNT(*) FROM orders')->fetchColumn();
$pendingOrders = (int) $pdo->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn();
$totalProducts = (int) $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn();
$lowStock = (int) $pdo->query('SELECT COUNT(*) FROM products WHERE stock <= 5 AND is_active = 1')->fetchColumn();

$recentOrders = $pdo->query('SELECT * FROM orders ORDER BY created_at DESC LIMIT 8')->fetchAll();

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="stat-grid">
  <div class="stat-card">
    <div class="label">Total Revenue</div>
    <div class="value"><?= money($totalRevenue) ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Total Orders</div>
    <div class="value"><?= $totalOrders ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Pending Orders</div>
    <div class="value"><?= $pendingOrders ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Products</div>
    <div class="value"><?= $totalProducts ?></div>
  </div>
  <div class="stat-card">
    <div class="label">Low Stock (≤5)</div>
    <div class="value"><?= $lowStock ?></div>
  </div>
</div>

<div class="admin-card">
  <div class="card-head">
    <h2>Recent Orders</h2>
    <a href="<?= base_path('admin/orders.php') ?>" class="btn btn-outline btn-sm">View all</a>
  </div>
  <div class="card-body" style="padding:0;">
    <table class="admin-table">
      <thead>
        <tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Date</th></tr>
      </thead>
      <tbody>
        <?php if (empty($recentOrders)): ?>
          <tr class="empty-row"><td colspan="6">No orders yet.</td></tr>
        <?php else: foreach ($recentOrders as $order): ?>
          <tr>
            <td><a href="<?= base_path('admin/order_detail.php?id=' . $order['id']) ?>">#<?= (int) $order['id'] ?></a></td>
            <td><?= h($order['full_name']) ?></td>
            <td><?= money((float) $order['total_amount']) ?></td>
            <td><span class="status-badge status-<?= h($order['payment_status']) ?>"><?= h($order['payment_status']) ?></span></td>
            <td><span class="status-badge status-<?= h($order['order_status']) ?>"><?= h($order['order_status']) ?></span></td>
            <td><?= date('M j, Y', strtotime($order['created_at'])) ?></td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
