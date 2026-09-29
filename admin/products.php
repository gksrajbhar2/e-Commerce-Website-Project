<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

$products = db()->query(
    'SELECT p.*, c.name AS category_name FROM products p
     LEFT JOIN categories c ON c.id = p.category_id
     ORDER BY p.created_at DESC'
)->fetchAll();

$pageTitle = 'Products';
$activeNav = 'products';
include __DIR__ . '/includes/admin_header.php';
?>

<div class="admin-card">
  <div class="card-head">
    <h2>All Products (<?= count($products) ?>)</h2>
    <a href="<?= base_path('admin/product_form.php') ?>" class="btn btn-primary btn-sm">+ Add product</a>
  </div>
  <div class="card-body" style="padding:0;">
    <table class="admin-table">
      <thead>
        <tr><th></th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr>
      </thead>
      <tbody>
        <?php if (empty($products)): ?>
          <tr class="empty-row"><td colspan="7">No products yet — add your first one.</td></tr>
        <?php else: foreach ($products as $p): ?>
          <tr>
            <td><img class="thumb" src="<?= h(product_image_url($p['image'])) ?>" alt=""></td>
            <td><?= h($p['name']) ?></td>
            <td><?= h($p['category_name'] ?? '—') ?></td>
            <td><?= money((float) $p['price']) ?></td>
            <td><?= (int) $p['stock'] ?></td>
            <td>
              <span class="status-badge <?= $p['is_active'] ? 'status-completed' : 'status-cancelled' ?>">
                <?= $p['is_active'] ? 'Active' : 'Hidden' ?>
              </span>
            </td>
            <td style="white-space:nowrap;">
              <a href="<?= base_path('admin/product_form.php?id=' . $p['id']) ?>" class="btn btn-outline btn-sm">Edit</a>
              <form method="post" action="<?= base_path('admin/product_delete.php') ?>" style="display:inline;"
                    onsubmit="return confirm('Delete this product? This cannot be undone.');">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" class="btn btn-danger btn-sm">Delete</button>
              </form>
            </td>
          </tr>
        <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
