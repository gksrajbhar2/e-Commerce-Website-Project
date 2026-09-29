<?php
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/helpers.php';

// The order id comes ONLY from the session (set by checkout.php), never
// from the query string — this stops a visitor from paying for / probing
// someone else's order by editing a URL.
$orderId = $_SESSION['pending_order_id'] ?? null;
if (!$orderId) {
    set_flash('error', 'No pending order found. Please check out again.');
    redirect('cart.php');
}

$stmt = db()->prepare('SELECT * FROM orders WHERE id = :id AND payment_method = "esewa" AND payment_status = "pending"');
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    set_flash('error', 'This order can no longer be paid for.');
    redirect('cart.php');
}

// eSewa ePay v2 required fields. amount + tax_amount + service charge +
// delivery charge must add up exactly to total_amount.
$fields = [
    'amount'                 => number_format((float) $order['subtotal'], 2, '.', ''),
    'tax_amount'              => '0',
    'product_service_charge'  => '0',
    'product_delivery_charge' => number_format((float) $order['delivery_charge'], 2, '.', ''),
    'total_amount'            => number_format((float) $order['total_amount'], 2, '.', ''),
    'transaction_uuid'        => $order['transaction_uuid'],
    'product_code'            => ESEWA_MERCHANT_CODE,
    'success_url'             => ESEWA_SUCCESS_URL,
    'failure_url'             => ESEWA_FAILURE_URL,
    'signed_field_names'      => 'total_amount,transaction_uuid,product_code',
];
$fields['signature'] = esewa_build_signature($fields, $fields['signed_field_names'], ESEWA_SECRET_KEY);

$pageTitle = 'Redirecting to eSewa';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Redirecting to eSewa…</title>
  <link rel="stylesheet" href="<?= base_path('assets/css/style.css') ?>">
</head>
<body>
  <div class="container" style="max-width:480px;margin-top:120px;text-align:center;">
    <h1>Redirecting you to eSewa…</h1>
    <p>Please wait while we take you to eSewa to complete your payment of <strong><?= money((float) $order['total_amount']) ?></strong>.</p>

    <form id="esewaForm" action="<?= h(ESEWA_FORM_URL) ?>" method="POST">
      <?php foreach ($fields as $name => $value): ?>
        <input type="hidden" name="<?= h($name) ?>" value="<?= h($value) ?>">
      <?php endforeach; ?>
      <noscript>
        <button type="submit" class="btn btn-primary">Continue to eSewa</button>
      </noscript>
    </form>
  </div>

  <script>document.getElementById('esewaForm').submit();</script>
</body>
</html>
