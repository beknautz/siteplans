<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
safe_session_start();
require_login();

// Admins only
if ((current_user()['role'] ?? '') !== 'admin') {
    flash_set('error', 'Access denied.');
    redirect(BASE_URL . '/admin/site-plans/index.php');
}

$error   = '';
$success = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $password =       $_POST['password'] ?? '';
        $role     = in_array($_POST['role'] ?? '', ['admin','user']) ? $_POST['role'] : 'user';

        if (!$name || !$email || !$password) {
            $error = 'Name, email, and password are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Enter a valid email address.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif (db_row('SELECT id FROM users WHERE email=:e', [':e' => $email])) {
            $error = 'That email is already registered.';
        } else {
            db_insert(
                'INSERT INTO users (name, email, password, role) VALUES (:n, :e, :p, :r)',
                [':n' => $name, ':e' => $email, ':p' => password_hash($password, PASSWORD_DEFAULT), ':r' => $role]
            );
            $success = 'User "' . h($name) . '" created.';
        }
    } elseif ($action === 'toggle') {
        $uid = (int)($_POST['uid'] ?? 0);
        $me  = current_user()['id'];
        if ($uid && $uid !== $me) {
            db_execute('UPDATE users SET active = 1 - active WHERE id = :id', [':id' => $uid]);
            $success = 'User status updated.';
        }
    } elseif ($action === 'reset_password') {
        $uid      = (int)($_POST['uid'] ?? 0);
        $password =       $_POST['new_password'] ?? '';
        if (!$uid || strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } else {
            db_execute(
                'UPDATE users SET password = :p WHERE id = :id',
                [':p' => password_hash($password, PASSWORD_DEFAULT), ':id' => $uid]
            );
            $success = 'Password updated.';
        }
    } elseif ($action === 'delete') {
        $uid = (int)($_POST['uid'] ?? 0);
        $me  = current_user()['id'];
        if ($uid && $uid !== $me) {
            db_execute('DELETE FROM users WHERE id = :id', [':id' => $uid]);
            $success = 'User deleted.';
        } else {
            $error = 'You cannot delete your own account.';
        }
    }
}

$users = db_rows('SELECT id, name, email, role, active, last_login, created_at FROM users ORDER BY created_at');
$page_title = 'Manage Users';
require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container py-4" style="max-width:860px;">
  <div class="d-flex justify-content-between align-items-center mb-3">
    <h4 class="mb-0"><i class="bi bi-people-fill me-2 text-primary"></i>Manage Users</h4>
    <a href="<?= BASE_URL ?>/admin/site-plans/index.php" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left me-1"></i>Back to Projects
    </a>
  </div>

  <?php if ($error): ?>
    <div class="alert alert-danger"><?= h($error) ?></div>
  <?php endif; ?>
  <?php if ($success): ?>
    <div class="alert alert-success"><?= h($success) ?></div>
  <?php endif; ?>

  <!-- User List -->
  <div class="card mb-4">
    <div class="card-header fw-semibold">Current Users</div>
    <div class="table-responsive">
      <table class="table table-hover table-sm mb-0">
        <thead class="table-dark">
          <tr>
            <th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last Login</th><th></th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <?php $isMe = $u['id'] === current_user()['id']; ?>
          <tr>
            <td><?= h($u['name']) ?> <?= $isMe ? '<span class="badge bg-secondary">You</span>' : '' ?></td>
            <td class="text-muted small"><?= h($u['email']) ?></td>
            <td>
              <span class="badge bg-<?= $u['role'] === 'admin' ? 'danger' : 'primary' ?>">
                <?= h(ucfirst($u['role'])) ?>
              </span>
            </td>
            <td>
              <span class="badge bg-<?= $u['active'] ? 'success' : 'secondary' ?>">
                <?= $u['active'] ? 'Active' : 'Disabled' ?>
              </span>
            </td>
            <td class="text-muted small"><?= $u['last_login'] ? date('M j, Y', strtotime($u['last_login'])) : 'Never' ?></td>
            <td class="text-end">
              <!-- Reset Password -->
              <button class="btn btn-outline-secondary btn-sm"
                      data-bs-toggle="modal" data-bs-target="#resetPwModal"
                      data-uid="<?= $u['id'] ?>" data-name="<?= h($u['name']) ?>">
                <i class="bi bi-key"></i>
              </button>
              <?php if (!$isMe): ?>
              <!-- Toggle active -->
              <form method="POST" class="d-inline">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-outline-<?= $u['active'] ? 'warning' : 'success' ?> btn-sm"
                        title="<?= $u['active'] ? 'Disable' : 'Enable' ?>">
                  <i class="bi bi-<?= $u['active'] ? 'pause-circle' : 'play-circle' ?>"></i>
                </button>
              </form>
              <!-- Delete -->
              <form method="POST" class="d-inline"
                    onsubmit="return confirm('Delete <?= h($u['name']) ?>? This cannot be undone.');">
                <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="uid" value="<?= $u['id'] ?>">
                <button type="submit" class="btn btn-outline-danger btn-sm">
                  <i class="bi bi-trash"></i>
                </button>
              </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Add User -->
  <div class="card">
    <div class="card-header fw-semibold">Add New User</div>
    <div class="card-body">
      <form method="POST" novalidate>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="create">
        <div class="row g-3">
          <div class="col-md-4">
            <label class="form-label">Full Name</label>
            <input type="text" name="name" class="form-control" required>
          </div>
          <div class="col-md-4">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" required autocomplete="off">
          </div>
          <div class="col-md-2">
            <label class="form-label">Role</label>
            <select name="role" class="form-select">
              <option value="user">User</option>
              <option value="admin">Admin</option>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Password</label>
            <input type="password" name="password" class="form-control" minlength="8" required autocomplete="new-password">
            <div class="form-text">Min 8 characters.</div>
          </div>
          <div class="col-12">
            <button type="submit" class="btn btn-primary">
              <i class="bi bi-person-plus me-1"></i>Create User
            </button>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Reset Password Modal -->
<div class="modal fade" id="resetPwModal" tabindex="-1">
  <div class="modal-dialog modal-sm">
    <div class="modal-content">
      <form method="POST">
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
        <input type="hidden" name="action" value="reset_password">
        <input type="hidden" name="uid" id="resetUid">
        <div class="modal-header">
          <h6 class="modal-title">Reset Password — <span id="resetName"></span></h6>
          <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <label class="form-label">New Password</label>
          <input type="password" name="new_password" class="form-control" minlength="8" required autocomplete="new-password">
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-warning btn-sm">Save</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script>
document.getElementById('resetPwModal').addEventListener('show.bs.modal', function (e) {
  const btn = e.relatedTarget;
  document.getElementById('resetUid').value  = btn.dataset.uid;
  document.getElementById('resetName').textContent = btn.dataset.name;
});
</script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
