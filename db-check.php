<?php
/**
 * db-check.php — Temporary DB connection diagnostic.
 * DELETE THIS FILE after fixing your config.
 */

// Try common shared-hosting connection variants
$tests = [
    ['host' => 'localhost',  'port' => 3306],
    ['host' => '127.0.0.1', 'port' => 3306],
    ['host' => 'localhost',  'port' => 3307],
    ['host' => '127.0.0.1', 'port' => 3307],
];

// ── Read current config values without running full bootstrap ──
$config_file = __DIR__ . '/config/config.php';
$config_src  = file_get_contents($config_file);

// Extract current values via regex (safe read-only)
$get = fn(string $k) => preg_match("/define\('$k'\s*,\s*'([^']*)'\)/", $config_src, $m) ? $m[1] : '?';

$cur_host = $get('DB_HOST');
$cur_user = $get('DB_USER');
$cur_pass = $get('DB_PASS');
$cur_name = $get('DB_NAME');
$cur_port = $get('DB_PORT');

// ── If a test form was submitted ──
$result = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $h = $_POST['host'] ?? '';
    $p = (int)($_POST['port'] ?? 3306);
    $u = $_POST['user'] ?? '';
    $w = $_POST['pass'] ?? '';
    $d = $_POST['name'] ?? '';
    try {
        $pdo = new PDO("mysql:host=$h;port=$p;dbname=$d;charset=utf8mb4", $u, $w, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);
        $ver = $pdo->query('SELECT VERSION()')->fetchColumn();
        $result = ['ok' => true, 'msg' => "Connected! MySQL $ver"];
    } catch (Exception $e) {
        $result = ['ok' => false, 'msg' => $e->getMessage()];
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>DB Check</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
</head>
<body class="bg-light p-4">
<div class="container" style="max-width:680px;">
  <h4 class="mb-1">Database Connection Checker</h4>
  <p class="text-danger fw-bold mb-3">Delete this file after fixing your config!</p>

  <!-- Current config -->
  <div class="card mb-4">
    <div class="card-header bg-secondary text-white">Current config/config.php values</div>
    <div class="card-body font-monospace small">
      DB_HOST = '<?= htmlspecialchars($cur_host) ?>'<br>
      DB_PORT = '<?= htmlspecialchars($cur_port) ?>'<br>
      DB_USER = '<?= htmlspecialchars($cur_user) ?>'<br>
      DB_PASS = '<?= str_repeat('*', strlen($cur_pass)) ?: '(empty)' ?>'<br>
      DB_NAME = '<?= htmlspecialchars($cur_name) ?>'
    </div>
  </div>

  <!-- Test form -->
  <div class="card mb-4">
    <div class="card-header">Test a connection</div>
    <div class="card-body">
      <?php if ($result): ?>
        <div class="alert alert-<?= $result['ok'] ? 'success' : 'danger' ?> mb-3">
          <?= htmlspecialchars($result['msg']) ?>
        </div>
      <?php endif; ?>
      <form method="POST">
        <div class="row g-2 mb-2">
          <div class="col-8">
            <input type="text" name="host" class="form-control form-control-sm" placeholder="Host"
                   value="<?= htmlspecialchars($_POST['host'] ?? $cur_host) ?>">
          </div>
          <div class="col-4">
            <input type="number" name="port" class="form-control form-control-sm" placeholder="Port"
                   value="<?= htmlspecialchars($_POST['port'] ?? $cur_port) ?>">
          </div>
          <div class="col-6">
            <input type="text" name="user" class="form-control form-control-sm" placeholder="Username"
                   value="<?= htmlspecialchars($_POST['user'] ?? $cur_user) ?>">
          </div>
          <div class="col-6">
            <input type="password" name="pass" class="form-control form-control-sm" placeholder="Password"
                   value="<?= htmlspecialchars($_POST['pass'] ?? $cur_pass) ?>">
          </div>
          <div class="col-12">
            <input type="text" name="name" class="form-control form-control-sm" placeholder="Database name"
                   value="<?= htmlspecialchars($_POST['name'] ?? $cur_name) ?>">
          </div>
        </div>
        <button type="submit" class="btn btn-primary btn-sm">Test Connection</button>
      </form>
    </div>
  </div>

  <div class="card">
    <div class="card-header">What to check in your hosting control panel</div>
    <div class="card-body small">
      <ol class="mb-0">
        <li>Go to your hosting control panel (cPanel / Plesk / SmarterASP.net / etc.)</li>
        <li>Find <strong>MySQL Databases</strong> — create a database if you haven't, note the <strong>exact database name</strong>.</li>
        <li>Create (or find) a <strong>MySQL User</strong> and assign it to the database with all privileges.</li>
        <li>Note the <strong>hostname</strong> shown in the control panel — on shared hosting it is often<br>
            <code>localhost</code>, <code>127.0.0.1</code>, or something like <code>sql123.yourhostingprovider.com</code>.</li>
        <li>Enter those values in the test form above until you see "Connected!"</li>
        <li>Then copy the working values into <code>config/config.php</code>.</li>
      </ol>
    </div>
  </div>
</div>
</body>
</html>
