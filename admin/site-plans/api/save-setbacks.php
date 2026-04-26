<?php
// API: Save setback rules for a project
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/helpers.php';
safe_session_start();
require_login_api();
csrf_check();

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$body   = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$project_id   = (int)($_GET['id'] ?? $body['project_id'] ?? 0);
$front_ft     = isset($body['front_ft'])     ? (float)$body['front_ft']     : 20.0;
$rear_ft      = isset($body['rear_ft'])      ? (float)$body['rear_ft']      : 5.0;
$side_ft      = isset($body['side_ft'])      ? (float)$body['side_ft']      : 5.0;
$accessory_ft = isset($body['accessory_ft']) ? (float)$body['accessory_ft'] : 5.0;
$well_sep_ft  = isset($body['well_sep_ft'])  ? (float)$body['well_sep_ft']  : 100.0;
$septic_sep_ft= isset($body['septic_sep_ft'])? (float)$body['septic_sep_ft']: 100.0;
$notes        = $body['notes'] ?? null;

if (!$project_id) {
    json_response(['success' => false, 'error' => 'project_id required'], 400);
}

db_execute(
    "INSERT INTO setback_rules
       (project_id, front_ft, rear_ft, side_ft, accessory_ft, well_sep_ft, septic_sep_ft, notes)
     VALUES
       (:pid, :front, :rear, :side, :acc, :well, :septic, :notes)
     ON DUPLICATE KEY UPDATE
       front_ft=VALUES(front_ft), rear_ft=VALUES(rear_ft),
       side_ft=VALUES(side_ft), accessory_ft=VALUES(accessory_ft),
       well_sep_ft=VALUES(well_sep_ft), septic_sep_ft=VALUES(septic_sep_ft),
       notes=VALUES(notes)",
    [
        ':pid'    => $project_id,
        ':front'  => $front_ft,
        ':rear'   => $rear_ft,
        ':side'   => $side_ft,
        ':acc'    => $accessory_ft,
        ':well'   => $well_sep_ft,
        ':septic' => $septic_sep_ft,
        ':notes'  => $notes,
    ]
);

json_response(['success' => true]);
