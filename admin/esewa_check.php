<?php
/**
 * Admin-only eSewa health check.
 * Runs from YOUR server (your PC when using XAMPP), so it tells you whether
 * this machine can actually reach eSewa and whether request signing works.
 */
require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../esewa/helpers.php';
require_admin();

$checks = [];
$add = function (string $title, string $state, string $detail) use (&$checks) {
    $checks[] = ['title' => $title, 'state' => $state, 'detail' => $detail]; // state: ok | warn | fail
};

// 1. PHP requirements -------------------------------------------------------
$hasCurl = function_exists('curl_init');
$add('PHP curl extension', $hasCurl ? 'ok' : 'fail',
    $hasCurl ? 'Enabled.' : 'Not enabled. In XAMPP open php.ini, remove the ";" before "extension=curl", then restart Apache/PHP.');
$hasSsl = extension_loaded('openssl');
$add('PHP openssl extension', $hasSsl ? 'ok' : 'fail',
    $hasSsl ? 'Enabled (needed for HTTPS).' : 'Not enabled. In php.ini remove the ";" before "extension=openssl" and restart.');

// 2. Signing self-test ------------------------------------------------------
// A genuine eSewa UAT response (published in eSewa SDK docs). If our code
// verifies it, both the secret key and the signing algorithm are correct.
if (ESEWA_ENV === 'test') {
    $sample = [
        'transaction_code'   => '000AWEO',
        'status'             => 'COMPLETE',
        'total_amount'       => '1000.0',
        'transaction_uuid'   => '250610-162413',
        'product_code'       => 'EPAYTEST',
        'signed_field_names' => 'transaction_code,status,total_amount,transaction_uuid,product_code,signed_field_names',
        'signature'          => '62GcfZTmVkzhtUeh+QJ1AqiJrjoWWGof3U+eTPTZ7fA=',
    ];
    $okSig = esewa_verify_response($sample);
    $add('Signature check (real eSewa sample)', $okSig ? 'ok' : 'fail',
        $okSig ? 'Secret key and HMAC-SHA256 signing match eSewa\'s.' : 'The sample did not verify — the secret key in config/esewa.php is not the eSewa UAT key.');

    // Same reply, but with the amount as a bare JSON number like eSewa often sends.
    $json    = json_encode($sample);
    $json    = str_replace('"total_amount":"1000.0"', '"total_amount":1000.0', $json);
    $decoded = esewa_decode_callback(base64_encode($json));
    $okNum   = $decoded !== null && esewa_verify_response($decoded);
    $add('Callback with numeric amount (1000.0)', $okNum ? 'ok' : 'fail',
        $okNum ? 'Amounts sent as bare numbers are read exactly as eSewa signed them.' : 'Numeric amounts are being altered before verification.');
} else {
    $add('Signature check', 'warn', 'Skipped: ESEWA_ENV is "live", so the public sandbox sample cannot be used.');
}

// 3. Can this server reach eSewa? ------------------------------------------
$statusUrl = ESEWA_STATUS_URL . '?' . http_build_query([
    'product_code' => ESEWA_MERCHANT_CODE, 'total_amount' => '100', 'transaction_uuid' => 'health-check',
]);
$res = esewa_http_get($statusUrl);
$json = $res['ok'] ? json_decode($res['body'], true) : null;
if (is_array($json) && isset($json['status'])) {
    $add('Status API reachable', 'ok', 'eSewa answered (HTTP ' . $res['http'] . ', status "' . $json['status'] . '"). The status endpoint itself is working.');
} elseif ($res['ok']) {
    $add('Status API reachable', 'warn', 'Got HTTP ' . $res['http'] . ' but not a normal eSewa reply. eSewa\'s sandbox may be down, or something is blocking the request. Reply started: ' . substr(strip_tags($res['body']), 0, 120));
} else {
    $hint = stripos($res['error'], 'SSL') !== false
        ? ' This is the common Windows/XAMPP "no CA bundle" problem: set ESEWA_VERIFY_SSL to false in config/esewa.php for local testing only (payments still work — this is a secondary check).'
        : ' Check your internet connection / firewall / antivirus.';
    $add('Status API reachable', 'fail', 'Could not connect: ' . $res['error'] . '.' . $hint);
}

$form = esewa_http_get(ESEWA_FORM_URL);
if (!$form['ok'] || $form['http'] === 0) {
    $add('Payment page reachable', 'fail', 'Could not connect to ' . ESEWA_FORM_URL . ': ' . ($form['error'] ?: 'no response') . '. Check your internet connection / firewall / antivirus.');
} elseif (in_array($form['http'], [401, 403, 407], true)) {
    $add('Payment page reachable', 'warn', 'Something answered with HTTP ' . $form['http'] . ' (access denied) — a firewall, proxy or antivirus may be blocking eSewa from this machine. Reply started: ' . substr(trim(strip_tags($form['body'])), 0, 100));
} elseif ($form['http'] >= 500) {
    $add('Payment page reachable', 'warn', 'eSewa responded with HTTP ' . $form['http'] . '. Their sandbox looks unavailable right now — that is what produces the "Service is currently unavailable" login error. Try again later, or use Cash on Delivery for the demo.');
} else {
    $add('Payment page reachable', 'ok', 'The eSewa payment host responded (HTTP ' . $form['http'] . '; a plain visit is expected to show an error page — real payments are POSTed).');
}

// 4. Config sanity ----------------------------------------------------------
$host    = $_SERVER['HTTP_HOST'] ?? '';
$baseHost = parse_url(BASE_URL, PHP_URL_HOST) . (parse_url(BASE_URL, PHP_URL_PORT) ? ':' . parse_url(BASE_URL, PHP_URL_PORT) : '');
$sameHost = $host === '' || strcasecmp($host, $baseHost) === 0;
$add('BASE_URL matches this site', $sameHost ? 'ok' : 'fail',
    $sameHost ? 'BASE_URL (' . BASE_URL . ') matches the address you are using.'
              : 'You opened the site at "' . $host . '" but BASE_URL is "' . BASE_URL . '". After paying, eSewa would send the customer to the wrong address. Edit BASE_URL in config/esewa.php.');

$pageTitle = 'eSewa check';
$activeNav = 'esewa';
include __DIR__ . '/includes/admin_header.php';

$labels = ['ok' => 'Pass', 'warn' => 'Check', 'fail' => 'Fail'];
$badge  = ['ok' => 'status-paid', 'warn' => 'status-pending', 'fail' => 'status-failed'];
?>

<div class="admin-card">
  <div class="card-head"><h2>Connection &amp; signing checks</h2></div>
  <div class="check-list">
    <?php foreach ($checks as $c): ?>
      <div class="check-row">
        <span class="status-badge <?= $badge[$c['state']] ?>"><?= $labels[$c['state']] ?></span>
        <div><strong><?= h($c['title']) ?></strong><p><?= h($c['detail']) ?></p></div>
      </div>
    <?php endforeach; ?>
  </div>
  <p class="form-note" style="padding:0 24px 20px;">Reload this page to run the checks again.</p>
</div>

<div class="admin-card" style="margin-top:20px;">
  <div class="card-head"><h2>Configuration in use</h2></div>
  <table class="admin-table kv">
    <tr><th>Mode</th><td><?= h(ESEWA_ENV) ?></td></tr>
    <tr><th>Merchant code</th><td><code><?= h(ESEWA_MERCHANT_CODE) ?></code></td></tr>
    <tr><th>Secret key</th><td><code><?= h(substr(ESEWA_SECRET_KEY, 0, 4)) ?>••••••••</code></td></tr>
    <tr><th>Payment URL</th><td><code><?= h(ESEWA_FORM_URL) ?></code></td></tr>
    <tr><th>Status URL</th><td><code><?= h(ESEWA_STATUS_URL) ?></code></td></tr>
    <tr><th>Success URL</th><td><code><?= h(ESEWA_SUCCESS_URL) ?></code></td></tr>
    <tr><th>Failure URL</th><td><code><?= h(ESEWA_FAILURE_URL) ?></code></td></tr>
  </table>
</div>

<?php if (ESEWA_ENV === 'test'): ?>
<div class="admin-card" style="margin-top:20px;">
  <div class="card-head"><h2>Sandbox logins for the eSewa payment page</h2></div>
  <table class="admin-table kv">
    <tr><th>Set A</th><td>eSewa ID <code>9806800001</code> (…02 to …05) · password <code>Nepal@123</code></td></tr>
    <tr><th>Set B</th><td>eSewa ID <code>9711111111</code> (…12, …13) · password <code>Test@123</code></td></tr>
    <tr><th>Both</th><td>MPIN <code>1122</code> · verification token <code>123456</code></td></tr>
  </table>
  <p class="form-note" style="padding:0 24px 20px;">eSewa's documentation lists both sets. If one is rejected, try the other. If eSewa shows "Service is currently unavailable" after you log in, that is eSewa's sandbox being down, not this site — try again later.</p>
</div>
<?php endif; ?>

<?php include __DIR__ . '/includes/admin_footer.php'; ?>
