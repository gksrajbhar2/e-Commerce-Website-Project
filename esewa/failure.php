<?php
/**
 * This is ESEWA_FAILURE_URL — eSewa sends the browser here if the customer
 * cancels or the payment fails on eSewa's side. We also land here from
 * success.php if our own verification rejects the callback.
 */
require_once __DIR__ . '/../includes/bootstrap.php';

// Best-effort: if we still know which order this was for, mark it failed
// (in case eSewa never sent us a data= callback at all, e.g. user just
// hit "back" from eSewa's page).
$orderId = $_SESSION['pending_order_id'] ?? null;
$order = null;
if ($orderId) {
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = :id');
    $stmt->execute(['id' => $orderId]);
    $order = $stmt->fetch();

    if ($order && $order['payment_status'] === 'pending') {
        db()->prepare('UPDATE orders SET payment_status = "failed" WHERE id = :id')->execute(['id' => $orderId]);
    }
}

$pageTitle = 'Payment Failed';
include __DIR__ . '/../includes/header.php';
?>

<div class="empty-state">
  <h1 style="color:var(--color-danger);">Payment didn't go through</h1>
  <p>Your order was not charged. Your cart is still saved, so you can try again or switch to Cash on Delivery.</p>
  <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:20px;">
    <a href="<?= base_path('checkout.php') ?>" class="btn btn-primary">Try again</a>
    <a href="<?= base_path('cart.php') ?>" class="btn btn-outline">Back to cart</a>
  </div>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
