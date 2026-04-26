<?php
// API: Update layer visibility for a project
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/helpers.php';
safe_session_start();
csrf_check();

header('Content-Type: application/json');

$body       = json_decode(file_get_contents('php://input'), true) ?? $_POST;
$project_id = (int)($body['project_id'] ?? 0);
$layer_key  = $body['layer_key'] ?? '';
$visible    = isset($body['visible']) ? (int)$body['visible'] : 1;

if (!$project_id || !$layer_key) {
    json_response(['success' => false, 'error' => 'project_id and layer_key required'], 400);
}

db_execute(
    "INSERT INTO site_plan_layers (project_id, layer_key, label, visible, sort_order)
     VALUES (:pid, :key, :key, :vis, 0)
     ON DUPLICATE KEY UPDATE visible = VALUES(visible)",
    [':pid' => $project_id, ':key' => $layer_key, ':vis' => $visible]
);

json_response(['success' => true]);
