<?php
// API: Save map viewport and calibration settings
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/helpers.php';
safe_session_start();
require_login_api();
csrf_check();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST required'], 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$project_id       = (int)($body['project_id']       ?? 0);
$center_lat       = isset($body['center_lat'])       ? (float)$body['center_lat']       : 46.6021;
$center_lng       = isset($body['center_lng'])       ? (float)$body['center_lng']       : -120.5059;
$zoom_level       = isset($body['zoom_level'])       ? (int)$body['zoom_level']         : 18;
$rotation         = isset($body['rotation'])         ? (float)$body['rotation']         : 0.0;
$basemap          = $body['basemap']          ?? 'esri';
$calibration_px   = isset($body['calibration_px']) && $body['calibration_px'] !== ''
                      ? (float)$body['calibration_px'] : null;
$calibration_ft   = isset($body['calibration_ft']) && $body['calibration_ft'] !== ''
                      ? (float)$body['calibration_ft'] : null;

$scale_factor = null;
if ($calibration_px && $calibration_ft && $calibration_px > 0) {
    $scale_factor = round($calibration_ft / $calibration_px, 6);
}

if (!$project_id) {
    json_response(['success' => false, 'error' => 'project_id required'], 400);
}

$valid_basemaps = ['esri','osm','mapbox','google'];
if (!in_array($basemap, $valid_basemaps, true)) $basemap = 'esri';

$zoom_level = max(1, min(22, $zoom_level));

$existing = db_row('SELECT id FROM map_settings WHERE project_id = :pid', [':pid' => $project_id]);

if ($existing) {
    db_execute(
        "UPDATE map_settings SET
           center_lat=:center_lat, center_lng=:center_lng,
           zoom_level=:zoom_level, rotation=:rotation, basemap=:basemap,
           calibration_px=:calibration_px, calibration_ft=:calibration_ft,
           scale_factor=:scale_factor
         WHERE project_id=:project_id",
        [
            ':center_lat'     => $center_lat,
            ':center_lng'     => $center_lng,
            ':zoom_level'     => $zoom_level,
            ':rotation'       => $rotation,
            ':basemap'        => $basemap,
            ':calibration_px' => $calibration_px,
            ':calibration_ft' => $calibration_ft,
            ':scale_factor'   => $scale_factor,
            ':project_id'     => $project_id,
        ]
    );
} else {
    db_insert(
        "INSERT INTO map_settings
           (project_id, center_lat, center_lng, zoom_level, rotation, basemap,
            calibration_px, calibration_ft, scale_factor)
         VALUES
           (:project_id, :center_lat, :center_lng, :zoom_level, :rotation, :basemap,
            :calibration_px, :calibration_ft, :scale_factor)",
        [
            ':project_id'     => $project_id,
            ':center_lat'     => $center_lat,
            ':center_lng'     => $center_lng,
            ':zoom_level'     => $zoom_level,
            ':rotation'       => $rotation,
            ':basemap'        => $basemap,
            ':calibration_px' => $calibration_px,
            ':calibration_ft' => $calibration_ft,
            ':scale_factor'   => $scale_factor,
        ]
    );
}

json_response([
    'success'      => true,
    'scale_factor' => $scale_factor,
    'basemap'      => $basemap,
]);
