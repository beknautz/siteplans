<?php
require_once dirname(__DIR__) . '/includes/helpers.php';
safe_session_start();

// Already logged in → go to app
if (is_logged_in()) {
    redirect(BASE_URL . '/admin/site-plans/index.php');
}

$error = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = trim($_POST['email']    ?? '');
    $password =       $_POST['password'] ?? '';

    if (!$email || !$password) {
        $error = 'Email and password are required.';
    } else {
        $user = db_row('SELECT * FROM users WHERE email = :e AND active = 1', [':e' => $email]);
        if ($user && password_verify($password, $user['password'])) {
            // Regenerate session ID on login (session fixation protection)
            session_regenerate_id(true);
            $_SESSION['user_id']   = $user['id'];
            $_SESSION['auth_user'] = [
                'id'    => $user['id'],
                'name'  => $user['name'],
                'email' => $user['email'],
                'role'  => $user['role'],
            ];
            // Record last login
            db_execute('UPDATE users SET last_login = NOW() WHERE id = :id', [':id' => $user['id']]);

            $next = $_POST['next'] ?? $_GET['next'] ?? '';
            redirect($next ?: BASE_URL . '/admin/site-plans/index.php');
        } else {
            $error = 'Invalid email or password.';
        }
    }
}

$next = h($_GET['next'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Login | <?= COMPANY ?></title>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <style>
    body { background: #1a2332; min-height: 100vh; display: flex; align-items: center; justify-content: center; }
    .login-card { width: 100%; max-width: 400px; }
    .login-logo { font-size: 2rem; color: #f0b429; }
  </style>
</head>
<body>
<div class="login-card mx-3">
  <div class="card shadow-lg border-0">
    <div class="card-body p-4">

      <div class="text-center mb-4">
        <div class="login-logo"><i class="bi bi-map-fill"></i></div>
        <h4 class="fw-bold mt-2 mb-0"><?= COMPANY ?></h4>
        <p class="text-muted small">Site Plan Tool</p>
      </div>

      <?php if ($error): ?>
        <div class="alert alert-danger py-2 small"><?= h($error) ?></div>
      <?php endif; ?>

      <form method="POST" novalidate>
        <input type="hidden" name="next" value="<?= $next ?>">

        <div class="mb-3">
          <label class="form-label fw-semibold" for="email">Email</label>
          <input id="email" type="email" name="email" class="form-control"
                 value="<?= h($email) ?>" required autofocus autocomplete="email">
        </div>

        <div class="mb-4">
          <label class="form-label fw-semibold" for="password">Password</label>
          <input id="password" type="password" name="password" class="form-control"
                 required autocomplete="current-password">
        </div>

        <button type="submit" class="btn btn-warning w-100 fw-bold">
          <i class="bi bi-box-arrow-in-right me-1"></i> Sign In
        </button>
      </form>

    </div>
  </div>
  <p class="text-center text-secondary small mt-3">
    &copy; <?= date('Y') ?> <?= COMPANY ?>
  </p>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
