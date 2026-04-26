<?php
// API: Save parcel boundary GeoJSON
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/helpers.php';
safe_session_start();
csrf_check();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST required'], 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$project_id     = (int)($body['project_id']   ?? 0);
$geojson        = $body['geojson']            ?? null;
$boundary_type  = $body['boundary_type']      ?? 'manual';
$label          = $body['label']              ?? '';
$lot_area_sqft  = isset($body['lot_area_sqft'])  ? (float)$body['lot_area_sqft']  : null;
$lot_area_acres = isset($body['lot_area_acres']) ? (float)$body['lot_area_acres'] : null;

if (!$project_id) {
    json_response(['success' => false, 'error' => 'project_id required'], 400);
}

if (is_array($geojson)) {
    $geojson = json_encode($geojson);
}

// Upsert: one parcel boundary per project (most recent wins)
$existing = db_row(
    'SELECT id FROM parcel_boundaries WHERE project_id = :pid ORDER BY id DESC LIMIT 1',
    [':pid' => $project_id]
);

if ($existing) {
    db_execute(
        "UPDATE parcel_boundaries SET
           geojson=:geojson, boundary_type=:bt, label=:label,
           lot_area_sqft=:sqft, lot_area_acres=:acres
         WHERE id=:id",
        [
            ':geojson' => $geojson,
            ':bt'      => $boundary_type,
            ':label'   => $label,
            ':sqft'    => $lot_area_sqft,
            ':acres'   => $lot_area_acres,
            ':id'      => $existing['id'],
        ]
    );
    json_response(['success' => true, 'id' => $existing['id']]);
} else {
    $new_id = db_insert(
        "INSERT INTO parcel_boundaries
           (project_id, geojson, boundary_type, label, lot_area_sqft, lot_area_acres)
         VALUES
           (:pid, :geojson, :bt, :label, :sqft, :acres)",
        [
            ':pid'    => $project_id,
            ':geojson'=> $geojson,
            ':bt'     => $boundary_type,
            ':label'  => $label,
            ':sqft'   => $lot_area_sqft,
            ':acres'  => $lot_area_acres,
        ]
    );
    json_response(['success' => true, 'id' => $new_id]);
}
