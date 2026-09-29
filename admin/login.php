<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_admin()) {
    redirect('admin/dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = db()->prepare('SELECT * FROM users WHERE email = :email AND role = "admin"');
    $stmt->execute(['email' => $email]);
    $admin = $stmt->fetch();

    if (!$admin || !password_verify($password, $admin['password'])) {
        $errors[] = 'Incorrect email or password.';
    } else {
        login_user($admin);
        redirect('admin/dashboard.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login · CircuitHub</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="<?= base_path('assets/css/admin.css') ?>">
</head>
<body class="admin admin-auth">

<div class="admin-login-wrap">
  <div class="auth-brand">Circuit<span>Hub</span><small>Admin</small></div>

  <div class="admin-login-card">
    <h1>Sign in</h1>
    <p class="sub">Manage products, orders and payments.</p>

    <?php foreach (get_flashes() as $flash): ?>
      <div class="alert alert-<?= h($flash['type']) ?>"><?= h($flash['message']) ?></div>
    <?php endforeach; ?>
    <?php if ($errors): ?>
      <div class="alert alert-error"><?php foreach ($errors as $e): ?><?= h($e) ?><?php endforeach; ?></div>
    <?php endif; ?>

    <form method="post">
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
    <p class="hint">Default seed login: admin@example.com / admin123</p>
  </div>
</div>

</body>
</html>
