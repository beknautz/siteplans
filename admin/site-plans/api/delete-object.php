<?php
// API: Soft-delete or hard-delete a site plan object
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/helpers.php';
safe_session_start();
require_login_api();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
if ($method !== 'DELETE' && $method !== 'POST') {
    json_response(['success' => false, 'error' => 'DELETE or POST required'], 405);
}

// Support both DELETE body and POST body
$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;
if ($method === 'DELETE') {
    parse_str(file_get_contents('php://input'), $delete_body);
    $body = $body ?? $delete_body;
}
$body = array_merge($_GET, $body ?? []);

csrf_check();

$id         = (int)($body['id']         ?? $_GET['id']         ?? 0);
$project_id = (int)($body['project_id'] ?? $_GET['project_id'] ?? 0);
$hard       = (bool)($body['hard']      ?? false);

if (!$id) {
    json_response(['success' => false, 'error' => 'id required'], 400);
}

if ($hard) {
    db_execute(
        'DELETE FROM site_plan_objects WHERE id=:id' . ($project_id ? ' AND project_id=:pid' : ''),
        $project_id ? [':id' => $id, ':pid' => $project_id] : [':id' => $id]
    );
} else {
    db_execute(
        'UPDATE site_plan_objects SET visible=0 WHERE id=:id' . ($project_id ? ' AND project_id=:pid' : ''),
        $project_id ? [':id' => $id, ':pid' => $project_id] : [':id' => $id]
    );
}

json_response(['success' => true, 'id' => $id]);
