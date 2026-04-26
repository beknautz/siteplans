<?php
// API: Load all project data as JSON (used by JS on page load refresh)
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/helpers.php';

header('Content-Type: application/json');

$project_id = (int)($_GET['id'] ?? 0);
if (!$project_id) {
    json_response(['success' => false, 'error' => 'id required'], 400);
}

$project = db_row('SELECT * FROM projects WHERE id=:id', [':id' => $project_id]);
if (!$project) {
    json_response(['success' => false, 'error' => 'Not found'], 404);
}

$map_settings = db_row('SELECT * FROM map_settings WHERE project_id=:pid', [':pid' => $project_id]);
$setbacks     = db_row('SELECT * FROM setback_rules WHERE project_id=:pid', [':pid' => $project_id]);
$layers       = db_rows('SELECT * FROM site_plan_layers WHERE project_id=:pid ORDER BY sort_order', [':pid' => $project_id]);
$objects      = db_rows('SELECT * FROM site_plan_objects WHERE project_id=:pid AND visible=1 ORDER BY sort_order,id', [':pid' => $project_id]);
$measurements = db_rows('SELECT * FROM site_plan_measurements WHERE project_id=:pid AND visible=1', [':pid' => $project_id]);
$parcel       = db_row('SELECT * FROM parcel_boundaries WHERE project_id=:pid ORDER BY id DESC LIMIT 1', [':pid' => $project_id]);

// Decode coordinates JSON for each object
foreach ($objects as &$obj) {
    if (!empty($obj['coordinates'])) {
        $obj['coordinates'] = json_decode($obj['coordinates'], true);
    }
}
unset($obj);

json_response([
    'success'      => true,
    'project'      => $project,
    'mapSettings'  => $map_settings,
    'setbacks'     => $setbacks,
    'layers'       => $layers,
    'objects'      => $objects,
    'measurements' => $measurements,
    'parcel'       => $parcel,
]);
