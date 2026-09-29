<?php
require_once __DIR__ . '/includes/bootstrap.php';

$items = get_cart_items();
if (empty($items)) {
    set_flash('error', 'Your cart is empty — add something before checking out.');
    redirect('cart.php');
}

$subtotal = get_cart_total();
$delivery = calculate_delivery_charge($subtotal);
$total    = $subtotal + $delivery;

$user = current_user();
$errors = [];

// Prefill from logged-in user, if any.
$fullName = $user['full_name'] ?? '';
$email    = $user['email'] ?? '';
$phone    = $user['phone'] ?? '';
$address  = $user['address'] ?? '';
$city     = '';
$paymentMethod = 'esewa';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $city     = trim($_POST['city'] ?? '');
    $paymentMethod = ($_POST['payment_method'] ?? 'esewa') === 'cod' ? 'cod' : 'esewa';

    if ($fullName === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if ($phone === '' || !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) $errors[] = 'A valid phone number is required.';
    if ($address === '') $errors[] = 'Delivery address is required.';
    if ($city === '') $errors[] = 'City is required.';

    // Re-check stock right before placing the order.
    foreach ($items as $item) {
        if ($item['quantity'] > $item['product']['stock']) {
            $errors[] = $item['product']['name'] . ' only has ' . $item['product']['stock'] . ' left in stock.';
        }
    }

    if (empty($errors)) {
        $pdo = db();
        try {
            $pdo->beginTransaction();

            $transactionUuid = generate_transaction_uuid();

            $stmt = $pdo->prepare(
                'INSERT INTO orders (user_id, transaction_uuid, full_name, email, phone, address, city,
                    subtotal, delivery_charge, total_amount, payment_method, payment_status, order_status)
                 VALUES (:user_id, :uuid, :name, :email, :phone, :address, :city,
                    :subtotal, :delivery, :total, :method, "pending", "pending")'
            );
            $stmt->execute([
                'user_id'  => $user['id'] ?? null,
                'uuid'     => $transactionUuid,
                'name'     => $fullName,
                'email'    => $email,
                'phone'    => $phone,
                'address'  => $address,
                'city'     => $city,
                'subtotal' => $subtotal,
                'delivery' => $delivery,
                'total'    => $total,
                'method'   => $paymentMethod,
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $itemStmt = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, price, quantity, subtotal)
                 VALUES (:order_id, :product_id, :name, :price, :qty, :subtotal)'
            );
            foreach ($items as $item) {
                $itemStmt->execute([
                    'order_id'   => $orderId,
                    'product_id' => $item['product']['id'],
                    'name'       => $item['product']['name'],
                    'price'      => $item['product']['price'],
                    'qty'        => $item['quantity'],
                    'subtotal'   => $item['subtotal'],
                ]);
            }

            $pdo->commit();
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log('Order creation failed: ' . $e->getMessage());
            $errors[] = 'Something went wrong placing your order. Please try again.';
        }

        if (empty($errors)) {
            // Store the pending order id in session — this is the only
            // way the payment pages identify "the order to act on", so a
            // visitor can't tamper with an order id via the URL.
            $_SESSION['pending_order_id'] = $orderId;

            if ($paymentMethod === 'cod') {
                db()->prepare('UPDATE orders SET order_status = "processing" WHERE id = :id')
                    ->execute(['id' => $orderId]);
                clear_cart();
                unset($_SESSION['pending_order_id']);
                $_SESSION['last_order_id'] = $orderId;
                redirect('order_success.php');
            }

            redirect('esewa/initiate.php');
        }
    }
}

$pageTitle = 'Checkout';
include __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">Checkout</h1>

<?php if ($errors): ?>
  <div class="alert alert-error">
    <strong>Please fix the following:</strong>
    <ul style="margin:8px 0 0;padding-left:18px;">
      <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
    </ul>
  </div>
<?php endif; ?>

<div class="cart-layout">
  <form method="post" class="form-card">
    <h3 class="mt-0">Shipping details</h3>
    <div class="form-grid">
      <div class="form-group full">
        <label for="full_name">Full name</label>
        <input type="text" id="full_name" name="full_name" value="<?= h($fullName) ?>" required>
      </div>
      <div class="form-group">
        <label for="email">Email</label>
        <input type="email" id="email" name="email" value="<?= h($email) ?>" required>
      </div>
      <div class="form-group">
        <label for="phone">Phone</label>
        <input type="tel" id="phone" name="phone" value="<?= h($phone) ?>" required>
      </div>
      <div class="form-group full">
        <label for="address">Delivery address</label>
        <input type="text" id="address" name="address" value="<?= h($address) ?>" required>
      </div>
      <div class="form-group">
        <label for="city">City</label>
        <input type="text" id="city" name="city" value="<?= h($city) ?>" required>
      </div>
    </div>

    <h3>Payment method</h3>
    <div class="payment-options">
      <label class="payment-option">
        <input type="radio" name="payment_method" value="esewa" <?= $paymentMethod === 'esewa' ? 'checked' : '' ?>>
        <div>
          <strong>eSewa</strong>
          <div class="form-hint">Pay instantly with your eSewa wallet. You'll be redirected to eSewa to complete payment.</div>
        </div>
      </label>
      <label class="payment-option">
        <input type="radio" name="payment_method" value="cod" <?= $paymentMethod === 'cod' ? 'checked' : '' ?>>
        <div>
          <strong>Cash on Delivery</strong>
          <div class="form-hint">Pay in cash when your order arrives.</div>
        </div>
      </label>
    </div>

    <?php if (ESEWA_ENV === 'test'): ?>
      <div class="test-hint">
        <strong>Test mode — no real money is used.</strong>
        <p>On the eSewa page, log in with one of these sandbox accounts (if the first is rejected, try the other):</p>
        <ul>
          <li><code>9806800001</code> · password <code>Nepal@123</code></li>
          <li><code>9711111111</code> · password <code>Test@123</code></li>
        </ul>
        <p>Then MPIN <code>1122</code> and verification token <code>123456</code>.</p>
      </div>
    <?php endif; ?>

    <button type="submit" class="btn btn-primary btn-block">Place order</button>
  </form>

  <div class="summary-card">
    <h3 class="mt-0">Order Summary</h3>
    <?php foreach ($items as $item): ?>
      <div class="summary-row">
        <span><?= h($item['product']['name']) ?> × <?= $item['quantity'] ?></span>
        <span><?= money($item['subtotal']) ?></span>
      </div>
    <?php endforeach; ?>
    <div class="summary-row"><span>Subtotal</span><span><?= money($subtotal) ?></span></div>
    <div class="summary-row"><span>Delivery</span><span><?= $delivery > 0 ? money($delivery) : 'Free' ?></span></div>
    <div class="summary-row total"><span>Total</span><span><?= money($total) ?></span></div>
  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
