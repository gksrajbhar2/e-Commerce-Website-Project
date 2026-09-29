<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email AND role = "customer"');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($password, $user['password'])) {
        $errors[] = 'Incorrect email or password.';
    } else {
        login_user($user);
        set_flash('success', 'Welcome back, ' . $user['full_name'] . '!');
        $redirectTo = $_SESSION['redirect_after_login'] ?? 'index.php';
        unset($_SESSION['redirect_after_login']);
        redirect($redirectTo);
    }
}

$pageTitle = 'Login';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <h1>Welcome back</h1>
  <p>Log in to view your orders and check out faster.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $err): ?><?= h($err) ?><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" class="form-card">
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= h($email) ?>" required autofocus>
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary btn-block">Log in</button>
  </form>

  <p class="auth-switch">Don't have an account? <a href="<?= base_path('auth/register.php') ?>">Create one</a></p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
