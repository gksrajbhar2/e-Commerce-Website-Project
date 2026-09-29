<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (is_logged_in()) {
    redirect('index.php');
}

$errors = [];
$fullName = $email = $phone = $address = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $fullName = trim($_POST['full_name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $phone    = trim($_POST['phone'] ?? '');
    $address  = trim($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm_password'] ?? '';

    if ($fullName === '') $errors[] = 'Full name is required.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'A valid email is required.';
    if (strlen($password) < 6) $errors[] = 'Password must be at least 6 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';

    if (empty($errors)) {
        $stmt = db()->prepare('SELECT id FROM users WHERE email = :email');
        $stmt->execute(['email' => $email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists.';
        }
    }

    if (empty($errors)) {
        $stmt = db()->prepare(
            'INSERT INTO users (full_name, email, password, phone, address, role)
             VALUES (:name, :email, :password, :phone, :address, "customer")'
        );
        $stmt->execute([
            'name'     => $fullName,
            'email'    => $email,
            'password' => password_hash($password, PASSWORD_BCRYPT),
            'phone'    => $phone ?: null,
            'address'  => $address ?: null,
        ]);

        login_user(['id' => db()->lastInsertId()]);
        set_flash('success', 'Welcome, ' . $fullName . '! Your account has been created.');
        redirect('index.php');
    }
}

$pageTitle = 'Create Account';
include __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <h1>Create your account</h1>
  <p>Save your details for faster checkout next time.</p>

  <?php if ($errors): ?>
    <div class="alert alert-error">
      <ul style="margin:0;padding-left:18px;">
        <?php foreach ($errors as $err): ?><li><?= h($err) ?></li><?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" class="form-card">
    <div class="form-group">
      <label for="full_name">Full name</label>
      <input type="text" id="full_name" name="full_name" value="<?= h($fullName) ?>" required>
    </div>
    <div class="form-group">
      <label for="email">Email</label>
      <input type="email" id="email" name="email" value="<?= h($email) ?>" required>
    </div>
    <div class="form-group">
      <label for="phone">Phone (optional)</label>
      <input type="tel" id="phone" name="phone" value="<?= h($phone) ?>">
    </div>
    <div class="form-group">
      <label for="address">Address (optional)</label>
      <input type="text" id="address" name="address" value="<?= h($address) ?>">
    </div>
    <div class="form-group">
      <label for="password">Password</label>
      <input type="password" id="password" name="password" required minlength="6">
    </div>
    <div class="form-group">
      <label for="confirm_password">Confirm password</label>
      <input type="password" id="confirm_password" name="confirm_password" required minlength="6">
    </div>
    <button type="submit" class="btn btn-primary btn-block">Create account</button>
  </form>

  <p class="auth-switch">Already have an account? <a href="<?= base_path('auth/login.php') ?>">Log in</a></p>
</div>

<?php include __DIR__ . '/../includes/footer.php'; ?>
