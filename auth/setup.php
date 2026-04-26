<?php
/**
 * auth/setup.php — One-time first admin user creation.
 * This page only works when NO users exist in the database.
 * Delete or rename this file after creating your account.
 */
require_once dirname(__DIR__) . '/includes/helpers.php';
safe_session_start();

// Block access once any user exists
$count = (int)(db_row('SELECT COUNT(*) cnt FROM users')['cnt'] ?? 0);
if ($count > 0) {
    // Already set up — go to login
    redirect(BASE_URL . '/auth/login.php');
}

$error   = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name     = trim($_POST['name']     ?? '');
    $email    = trim($_POST['email']    ?? '');
    $password =       $_POST['password'] ?? '';
    $confirm  =       $_POST['confirm']  ?? '';

    if (!$name || !$email || !$password) {
        $error = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db_insert(
            'INSERT INTO users (name, email, password, role) VALUES (:n, :e, :p, :r)',
            [':n' => $name, ':e' => $email, ':p' => $hash, ':r' => 'admin']
        );
        $success = true;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>First-Time Setup | <?= COMPANY ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: #1a2332; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .setup-card { width: 100%; max-width: 440px; }
  </style>
</head>
<body>
<div class="setup-card mx-3">
  <div class="card shadow-lg border-0">
    <div class="card-body p-4">

      <div class="text-center mb-4">
        <i class="bi bi-shield-lock-fill text-warning" style="font-size:2rem;"></i>
        <h4 class="fw-bold mt-2 mb-0">First-Time Setup</h4>
        <p class="text-muted small">Create your admin account</p>
      </div>

      <?php if ($success): ?>
        <div class="alert alert-success">
          <i class="bi bi-check-circle-fill me-2"></i>
          Admin account created. <strong>Delete <code>auth/setup.php</code></strong> from the server now.
          <div class="mt-2">
            <a href="<?= BASE_URL ?>/auth/login.php" class="btn btn-success btn-sm">Go to Login</a>
          </div>
        </div>
      <?php else: ?>

        <?php if ($error): ?>
          <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="POST" novalidate>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="name">Full Name</label>
            <input id="name" type="text" name="name" class="form-control"
                   value="<?= h($_POST['name'] ?? '') ?>" required autofocus>
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="email">Email</label>
            <input id="email" type="email" name="email" class="form-control"
                   value="<?= h($_POST['email'] ?? '') ?>" required autocomplete="email">
          </div>
          <div class="mb-3">
            <label class="form-label fw-semibold" for="password">Password</label>
            <input id="password" type="password" name="password" class="form-control"
                   required minlength="8" autocomplete="new-password">
            <div class="form-text">Minimum 8 characters.</div>
          </div>
          <div class="mb-4">
            <label class="form-label fw-semibold" for="confirm">Confirm Password</label>
            <input id="confirm" type="password" name="confirm" class="form-control"
                   required autocomplete="new-password">
          </div>
          <button type="submit" class="btn btn-warning w-100 fw-bold">
            <i class="bi bi-person-plus-fill me-1"></i> Create Admin Account
          </button>
        </form>

      <?php endif; ?>

    </div>
  </div>
  <p class="text-center text-secondary small mt-3">
    &copy; <?= date('Y') ?> <?= COMPANY ?>
  </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
