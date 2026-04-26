<?php
// API: Delete a project (HTMX DELETE handler)
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/helpers.php';
safe_session_start();
csrf_check();

header('Content-Type: text/html');

$id = (int)($_GET['id'] ?? 0);

if (!$id) {
    http_response_code(400);
    echo '<tr><td colspan="10" class="text-danger text-center">Invalid project ID.</td></tr>';
    exit;
}

db_execute('DELETE FROM projects WHERE id = :id', [':id' => $id]);

// Return empty string so HTMX replaces the row with nothing
http_response_code(200);
echo '';
exit;
