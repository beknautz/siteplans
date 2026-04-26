<?php
/**
 * diag.php — BaceBuilt Site Plan Diagnostic
 * Visit https://crm.bacebuilt.com/diag.php to see what is failing.
 * DELETE THIS FILE after the site is working.
 */
error_reporting(E_ALL);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');

$pass = '✅';
$fail = '❌';

?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>BaceBuilt Diagnostic</title>
<style>
  body { font-family: monospace; padding: 30px; background: #111; color: #eee; }
  h2   { color: #f39c12; }
  .ok  { color: #2ecc71; }
  .err { color: #e74c3c; }
  .warn{ color: #f39c12; }
  table{ border-collapse: collapse; width: 100%; margin-bottom: 20px; }
  td,th{ border: 1px solid #444; padding: 6px 12px; text-align: left; }
  th   { background: #222; color: #aaa; }
  pre  { background: #1a1a1a; padding: 12px; border-radius: 4px; overflow-x: auto; color: #0f0; }
</style>
</head>
<body>
<h1>🛠 BaceBuilt Site Plan — Server Diagnostic</h1>

<!-- ── 1. PHP ── -->
<h2>1. PHP</h2>
<table>
<tr><th>Check</th><th>Result</th></tr>
<tr>
  <td>PHP Version</td>
  <td class="<?= version_compare(PHP_VERSION, '8.0.0', '>=') ? 'ok' : 'err' ?>">
    <?= PHP_VERSION ?>
    <?= version_compare(PHP_VERSION, '8.0.0', '>=') ? '(8.0+ required ✅)' : '(need PHP 8.0+ ❌)' ?>
  </td>
</tr>
<tr>
  <td>PDO extension</td>
  <td class="<?= extension_loaded('pdo') ? 'ok' : 'err' ?>">
    <?= extension_loaded('pdo') ? "$pass loaded" : "$fail NOT loaded — enable extension=pdo in php.ini" ?>
  </td>
</tr>
<tr>
  <td>PDO MySQL driver</td>
  <td class="<?= extension_loaded('pdo_mysql') ? 'ok' : 'err' ?>">
    <?= extension_loaded('pdo_mysql') ? "$pass loaded" : "$fail NOT loaded — enable extension=pdo_mysql in php.ini" ?>
  </td>
</tr>
<tr>
  <td>JSON extension</td>
  <td class="<?= extension_loaded('json') ? 'ok' : 'err' ?>">
    <?= extension_loaded('json') ? "$pass loaded" : "$fail NOT loaded" ?>
  </td>
</tr>
<tr>
  <td>Session extension</td>
  <td class="<?= extension_loaded('session') ? 'ok' : 'err' ?>">
    <?= extension_loaded('session') ? "$pass loaded" : "$fail NOT loaded" ?>
  </td>
</tr>
<tr>
  <td>php.ini path</td>
  <td><?= php_ini_loaded_file() ?: 'none found' ?></td>
</tr>
<tr>
  <td>Server software</td>
  <td><?= $_SERVER['SERVER_SOFTWARE'] ?? 'unknown' ?></td>
</tr>
<tr>
  <td>Document root</td>
  <td><?= $_SERVER['DOCUMENT_ROOT'] ?? 'unknown' ?></td>
</tr>
<tr>
  <td>Script path (__FILE__)</td>
  <td><?= __FILE__ ?></td>
</tr>
</table>

<!-- ── 2. Session ── -->
<h2>2. Session</h2>
<?php
$sessionOk = false;
try {
    session_start();
    $_SESSION['diag_test'] = 1;
    $sessionOk = isset($_SESSION['diag_test']);
    session_write_close();
    echo "<p class='ok'>$pass Sessions working. Save path: <code>" . session_save_path() . "</code></p>";
} catch (Throwable $e) {
    echo "<p class='err'>$fail Session failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<p class='warn'>Fix: set <code>session.save_path</code> in php.ini to a writable directory, e.g. <code>C:\\Windows\\Temp</code></p>";
}
?>

<!-- ── 3. File paths ── -->
<h2>3. File Paths</h2>
<table>
<tr><th>File</th><th>Exists?</th></tr>
<?php
$base  = dirname(__FILE__);
$files = [
    'config/config.php',
    'config/database.php',
    'includes/helpers.php',
    'includes/header.php',
    'includes/footer.php',
    'admin/site-plans/index.php',
    'admin/site-plans/edit.php',
    'admin/site-plans/create.php',
    'assets/css/site-plans.css',
    'assets/js/map-init.js',
];
foreach ($files as $f):
    $exists = file_exists($base . '/' . $f);
?>
<tr>
  <td><?= $f ?></td>
  <td class="<?= $exists ? 'ok' : 'err' ?>"><?= $exists ? "$pass exists" : "$fail MISSING" ?></td>
</tr>
<?php endforeach; ?>
</table>

<!-- ── 4. Config load ── -->
<h2>4. Config Load</h2>
<?php
try {
    require_once __DIR__ . '/config/config.php';
    echo "<p class='ok'>$pass config/config.php loaded</p>";
    echo "<table>";
    echo "<tr><th>Constant</th><th>Value</th></tr>";
    foreach (['APP_NAME','BASE_URL','BASE_PATH','DB_HOST','DB_NAME','DB_USER','DB_PORT'] as $c) {
        $val = defined($c) ? constant($c) : '(not defined)';
        // Mask password
        echo "<tr><td>$c</td><td>" . htmlspecialchars($c === 'DB_PASS' ? '***' : (string)$val) . "</td></tr>";
    }
    echo "</table>";
} catch (Throwable $e) {
    echo "<p class='err'>$fail config/config.php failed: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

<!-- ── 5. Database ── -->
<h2>5. Database Connection</h2>
<?php
if (defined('DB_HOST')) {
    try {
        $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        echo "<p class='ok'>$pass Connected to MySQL at <code>" . DB_HOST . "</code>, database <code>" . DB_NAME . "</code></p>";

        // Check tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
        echo "<p>Tables found: <strong>" . implode(', ', $tables ?: ['(none)']) . "</strong></p>";
        if (!in_array('projects', $tables)) {
            echo "<p class='err'>$fail Table <code>projects</code> missing — run <code>setup.sql</code> to create tables.</p>";
        } else {
            $count = $pdo->query("SELECT COUNT(*) FROM projects")->fetchColumn();
            echo "<p class='ok'>$pass <code>projects</code> table has $count row(s)</p>";
        }
    } catch (PDOException $e) {
        echo "<p class='err'>$fail DB connection failed: " . htmlspecialchars($e->getMessage()) . "</p>";
        echo "<p class='warn'>Check DB_HOST, DB_NAME, DB_USER, DB_PASS in <code>config/config.php</code></p>";
    }
} else {
    echo "<p class='warn'>⚠ Config not loaded — fix step 4 first</p>";
}
?>

<!-- ── 6. Helpers load ── -->
<h2>6. Helpers / Bootstrap</h2>
<?php
if (!function_exists('h')) {
    try {
        require_once __DIR__ . '/includes/helpers.php';
        echo "<p class='ok'>$pass includes/helpers.php loaded — h(), db_row(), etc. available</p>";
    } catch (Throwable $e) {
        echo "<p class='err'>$fail helpers.php failed: " . htmlspecialchars($e->getMessage()) . "</p>";
    }
} else {
    echo "<p class='ok'>$pass helpers already loaded</p>";
}
?>

<!-- ── 7. Writable dirs ── -->
<h2>7. Writable Directories</h2>
<table>
<tr><th>Directory</th><th>Writable?</th></tr>
<?php
$dirs = [
    dirname(__FILE__) . '/exports' => 'exports/',
    sys_get_temp_dir()             => 'sys temp (' . sys_get_temp_dir() . ')',
];
foreach ($dirs as $path => $label):
    $ok = is_dir($path) && is_writable($path);
?>
<tr>
  <td><?= htmlspecialchars($label) ?></td>
  <td class="<?= $ok ? 'ok' : 'warn' ?>"><?= $ok ? "$pass writable" : "⚠ not writable (create dir or chmod)" ?></td>
</tr>
<?php endforeach; ?>
</table>

<hr>
<p style="color:#666;font-size:.8em;">
  Delete <code>diag.php</code> after fixing all issues.
</p>
</body>
</html>
