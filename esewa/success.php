<?php
/**
 * This is ESEWA_SUCCESS_URL — eSewa redirects the customer's browser here
 * (GET) after a completed payment, with a base64-encoded JSON blob in
 * ?data=. We must NOT trust that blob until its signature checks out.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/helpers.php';

function fail(string $reason, ?array $order = null, ?array $rawData = null): void
{
    error_log('eSewa payment verification failed: ' . $reason);

    if ($order && $order['payment_status'] === 'pending') {
        db()->prepare('UPDATE orders SET payment_status = "failed" WHERE id = :id')
            ->execute(['id' => $order['id']]);

        db()->prepare(
            'INSERT INTO payments (order_id, transaction_uuid, transaction_code, status, raw_response)
             VALUES (:order_id, :uuid, :code, :status, :raw)'
        )->execute([
            'order_id' => $order['id'],
            'uuid'     => $order['transaction_uuid'],
            'code'     => $rawData['transaction_code'] ?? null,
            'status'   => 'FAILED',
            'raw'      => json_encode(['reason' => $reason, 'data' => $rawData]),
        ]);
    }

    set_flash('error', 'We could not confirm your eSewa payment (' . $reason . '). Please try again or contact support.');
    redirect('esewa/failure.php');
}

$encoded = $_GET['data'] ?? '';
if ($encoded === '') {
    fail('missing response data');
}

$data = esewa_decode_callback($encoded);

if (!is_array($data)) {
    fail('unreadable response data');
}

if (!esewa_verify_response($data)) {
    fail('signature mismatch — response may have been tampered with', null, $data);
}

$transactionUuid = $data['transaction_uuid'] ?? '';
$stmt = db()->prepare('SELECT * FROM orders WHERE transaction_uuid = :uuid');
$stmt->execute(['uuid' => $transactionUuid]);
$order = $stmt->fetch();

if (!$order) {
    fail('no matching order for transaction ' . $transactionUuid, null, $data);
}

// Already processed (e.g. user refreshed this page) — just show the confirmation again.
if ($order['payment_status'] === 'paid') {
    $_SESSION['last_order_id'] = $order['id'];
    unset($_SESSION['pending_order_id']);
    redirect('order_success.php');
}

if (($data['product_code'] ?? '') !== ESEWA_MERCHANT_CODE) {
    fail('product code mismatch', $order, $data);
}

$responseTotal = isset($data['total_amount']) ? esewa_amount_to_float($data['total_amount']) : null;
if ($responseTotal === null || abs($responseTotal - (float) $order['total_amount']) > 0.01) {
    fail('amount mismatch', $order, $data);
}

if (($data['status'] ?? '') !== 'COMPLETE') {
    fail('status was "' . ($data['status'] ?? 'unknown') . '", not COMPLETE', $order, $data);
}

// Secondary confirmation via eSewa's server-to-server status check. The
// signature check above is already the authoritative verification (only
// eSewa holds the secret key), so this is treated as "inconclusive" — not
// fatal — if the request itself fails or eSewa is still settling
// (PENDING / AMBIGUOUS). We only refuse when eSewa explicitly says the money
// did not stay with us.
$statusCheck = esewa_check_status($order['transaction_uuid'], number_format((float) $order['total_amount'], 2, '.', ''));
if (is_array($statusCheck) && isset($statusCheck['status'])) {
    if (in_array($statusCheck['status'], ['CANCELED', 'FULL_REFUND', 'NOT_FOUND'], true)) {
        fail('status check returned "' . $statusCheck['status'] . '"', $order, $data);
    }
    if ($statusCheck['status'] !== 'COMPLETE') {
        error_log('eSewa status check for ' . $order['transaction_uuid'] . ' returned ' . $statusCheck['status'] . ' — accepting the signed COMPLETE callback.');
    }
}

// All checks passed — mark the order paid and decrement stock.
$pdo = db();
try {
    $pdo->beginTransaction();

    $pdo->prepare('UPDATE orders SET payment_status = "paid", order_status = "processing" WHERE id = :id')
        ->execute(['id' => $order['id']]);

    $pdo->prepare(
        'INSERT INTO payments (order_id, transaction_uuid, transaction_code, status, raw_response)
         VALUES (:order_id, :uuid, :code, :status, :raw)'
    )->execute([
        'order_id' => $order['id'],
        'uuid'     => $order['transaction_uuid'],
        'code'     => $data['transaction_code'] ?? null,
        'status'   => 'COMPLETE',
        'raw'      => json_encode($data),
    ]);

    $itemStmt = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = :id');
    $itemStmt->execute(['id' => $order['id']]);
    $updateStock = $pdo->prepare('UPDATE products SET stock = GREATEST(stock - :qty, 0) WHERE id = :pid');
    foreach ($itemStmt->fetchAll() as $line) {
        if ($line['product_id']) {
            $updateStock->execute(['qty' => $line['quantity'], 'pid' => $line['product_id']]);
        }
    }

    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    error_log('Failed to finalize paid order #' . $order['id'] . ': ' . $e->getMessage());
    fail('internal error finalizing order', $order, $data);
}

clear_cart();
unset($_SESSION['pending_order_id']);
$_SESSION['last_order_id'] = $order['id'];
redirect('order_success.php');
