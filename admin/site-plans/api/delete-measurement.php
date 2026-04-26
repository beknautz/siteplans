<?php
// API: Delete a measurement record
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/helpers.php';
safe_session_start();
csrf_check();

header('Content-Type: application/json');

$id         = (int)($_GET['id']         ?? 0);
$project_id = (int)($_GET['project_id'] ?? 0);

if (!$id) json_response(['success' => false, 'error' => 'id required'], 400);

$sql    = 'DELETE FROM site_plan_measurements WHERE id = :id';
$params = [':id' => $id];
if ($project_id) {
    $sql   .= ' AND project_id = :pid';
    $params[':pid'] = $project_id;
}
db_execute($sql, $params);

json_response(['success' => true]);
