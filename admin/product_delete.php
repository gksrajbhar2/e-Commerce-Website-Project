<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id) {
        // Soft-delete: keep historical order_items pointing at real rows,
        // but keep it simple here — order_items.product_id is ON DELETE
        // SET NULL, so a hard delete is safe too. We hide instead of
        // deleting so past invoices still show the product name/price.
        db()->prepare('UPDATE products SET is_active = 0 WHERE id = :id')->execute(['id' => $id]);
        set_flash('success', 'Product removed from the store.');
    }
}

redirect('admin/products.php');
