<?php
require_once __DIR__ . '/includes/bootstrap.php';
require_login();

$user   = current_user();
$userId = (int) $user['id'];
$pdo    = db();

$profileErrors  = [];
$passwordErrors = [];

$fullName = $user['full_name'];
$phone    = $user['phone'] ?? '';
$address  = $user['address'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        set_flash('error', 'Your session expired — please try again.');
        redirect('profile.php');
    }

    $action = $_POST['action'] ?? '';

    // ---- Update personal details ----
    if ($action === 'update_profile') {
        $fullName = trim($_POST['full_name'] ?? '');
        $phone    = trim($_POST['phone'] ?? '');
        $address  = trim($_POST['address'] ?? '');

        if ($fullName === '') $profileErrors[] = 'Full name is required.';
        if ($phone !== '' && !preg_match('/^[0-9+\-\s]{7,20}$/', $phone)) {
            $profileErrors[] = 'Please enter a valid phone number.';
        }

        if (empty($profileErrors)) {
            $pdo->prepare('UPDATE users SET full_name = :n, phone = :p, address = :a WHERE id = :id')
                ->execute(['n' => $fullName, 'p' => $phone ?: null, 'a' => $address ?: null, 'id' => $userId]);
            set_flash('success', 'Your details have been updated.');
            redirect('profile.php');
        }
    }

    // ---- Change password ----
    if ($action === 'change_password') {
        $current = $_POST['current_password'] ?? '';
        $new     = $_POST['new_password'] ?? '';
        $confirm = $_POST['confirm_password'] ?? '';

        $row = $pdo->prepare('SELECT password FROM users WHERE id = :id');
        $row->execute(['id' => $userId]);
        $hash = (string) $row->fetchColumn();

        if (!password_verify($current, $hash)) $passwordErrors[] = 'Your current password is incorrect.';
        if (strlen($new) < 6)                  $passwordErrors[] = 'New password must be at least 6 characters.';
        if ($new !== $confirm)                 $passwordErrors[] = 'New passwords do not match.';

        if (empty($passwordErrors)) {
            $pdo->prepare('UPDATE users SET password = :p WHERE id = :id')
                ->execute(['p' => password_hash($new, PASSWORD_BCRYPT), 'id' => $userId]);
            set_flash('success', 'Password changed successfully.');
            redirect('profile.php');
        }
    }
}

// ---- Account summary + latest orders ----
$statsStmt = $pdo->prepare(
    "SELECT COUNT(*) AS order_count,
            COALESCE(SUM(CASE WHEN payment_status = 'paid' THEN total_amount END), 0) AS total_paid
     FROM orders WHERE user_id = :id"
);
$statsStmt->execute(['id' => $userId]);
$stats = $statsStmt->fetch();

$recentStmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = :id ORDER BY created_at DESC LIMIT 5');
$recentStmt->execute(['id' => $userId]);
$recent = $recentStmt->fetchAll();

$memberStmt = $pdo->prepare('SELECT created_at FROM users WHERE id = :id');
$memberStmt->execute(['id' => $userId]);
$memberSince = (string) $memberStmt->fetchColumn();

$pageTitle = 'My Profile';
include __DIR__ . '/includes/header.php';
?>

<h1 class="page-title">My Profile</h1>

<div class="profile-layout">

  <aside class="profile-side">
    <div class="profile-card">
      <div class="avatar" aria-hidden="true"><?= h(name_initial($user['full_name'])) ?></div>
      <h2><?= h($user['full_name']) ?></h2>
      <p class="profile-email"><?= h($user['email']) ?></p>
      <?php if ($memberSince): ?>
        <p class="profile-since">Member since <?= date('F Y', strtotime($memberSince)) ?></p>
      <?php endif; ?>

      <div class="profile-stats">
        <div><strong><?= (int) $stats['order_count'] ?></strong><span>Orders</span></div>
        <div><strong><?= money((float) $stats['total_paid']) ?></strong><span>Paid so far</span></div>
      </div>

      <a href="<?= base_path('orders.php') ?>" class="btn btn-outline btn-block btn-sm">View all orders</a>
    </div>
  </aside>

  <div class="profile-main">

    <section class="form-card">
      <h3 class="mt-0">Personal details</h3>

      <?php if ($profileErrors): ?>
        <div class="alert alert-error">
          <ul style="margin:0;padding-left:18px;">
            <?php foreach ($profileErrors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_profile">
        <div class="form-grid">
          <div class="form-group">
            <label for="full_name">Full name</label>
            <input type="text" id="full_name" name="full_name" value="<?= h($fullName) ?>" required>
          </div>
          <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" value="<?= h($user['email']) ?>" disabled>
            <div class="form-hint">Your email is your login and can't be changed here.</div>
          </div>
          <div class="form-group">
            <label for="phone">Phone</label>
            <input type="tel" id="phone" name="phone" value="<?= h($phone) ?>" placeholder="98XXXXXXXX">
          </div>
          <div class="form-group">
            <label for="address">Delivery address</label>
            <input type="text" id="address" name="address" value="<?= h($address) ?>" placeholder="Street, area">
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Save changes</button>
      </form>
    </section>

    <section class="form-card">
      <h3 class="mt-0">Recent orders</h3>
      <?php if (empty($recent)): ?>
        <p style="color:var(--color-ink-soft);margin:0;">You haven't placed any orders yet.
          <a href="<?= base_path('index.php') ?>" style="color:var(--color-forest);font-weight:600;">Start shopping</a></p>
      <?php else: ?>
        <div class="table-wrap">
          <table class="cart-table plain">
            <thead><tr><th>Order</th><th>Date</th><th>Total</th><th>Payment</th><th>Status</th><th></th></tr></thead>
            <tbody>
              <?php foreach ($recent as $o): ?>
                <tr>
                  <td>#<?= (int) $o['id'] ?></td>
                  <td><?= date('M j, Y', strtotime($o['created_at'])) ?></td>
                  <td><?= money((float) $o['total_amount']) ?></td>
                  <td><span class="status-badge status-<?= h($o['payment_status']) ?>"><?= h($o['payment_status']) ?></span></td>
                  <td><span class="status-badge status-<?= h($o['order_status']) ?>"><?= h($o['order_status']) ?></span></td>
                  <td><a href="<?= base_path('order.php?id=' . (int) $o['id']) ?>" class="btn btn-outline btn-sm">Details</a></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>

    <section class="form-card">
      <h3 class="mt-0">Change password</h3>

      <?php if ($passwordErrors): ?>
        <div class="alert alert-error">
          <ul style="margin:0;padding-left:18px;">
            <?php foreach ($passwordErrors as $e): ?><li><?= h($e) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="change_password">
        <div class="form-group">
          <label for="current_password">Current password</label>
          <input type="password" id="current_password" name="current_password" required autocomplete="current-password">
        </div>
        <div class="form-grid">
          <div class="form-group">
            <label for="new_password">New password</label>
            <input type="password" id="new_password" name="new_password" required minlength="6" autocomplete="new-password">
          </div>
          <div class="form-group">
            <label for="confirm_password">Confirm new password</label>
            <input type="password" id="confirm_password" name="confirm_password" required minlength="6" autocomplete="new-password">
          </div>
        </div>
        <button type="submit" class="btn btn-primary">Update password</button>
      </form>
    </section>

  </div>
</div>

<?php include __DIR__ . '/includes/footer.php'; ?>
