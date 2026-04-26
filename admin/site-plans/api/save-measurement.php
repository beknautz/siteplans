<?php
// API: Create or update a distance measurement
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

$project_id     = (int)($body['project_id']     ?? 0);
$meas_id        = (int)($body['id']             ?? 0);
$label          = $body['label']          ?? '';
$from_lat       = isset($body['from_lat'])       ? (float)$body['from_lat']       : null;
$from_lng       = isset($body['from_lng'])       ? (float)$body['from_lng']       : null;
$to_lat         = isset($body['to_lat'])         ? (float)$body['to_lat']         : null;
$to_lng         = isset($body['to_lng'])         ? (float)$body['to_lng']         : null;
$from_x         = isset($body['from_x'])         ? (float)$body['from_x']         : null;
$from_y         = isset($body['from_y'])         ? (float)$body['from_y']         : null;
$to_x           = isset($body['to_x'])           ? (float)$body['to_x']           : null;
$to_y           = isset($body['to_y'])           ? (float)$body['to_y']           : null;
$geometry_type  = $body['geometry_type']  ?? 'latlng';
$calculated_ft  = isset($body['calculated_ft'])  ? (float)$body['calculated_ft']  : null;
$override_ft    = isset($body['override_ft']) && $body['override_ft'] !== ''
                    ? (float)$body['override_ft'] : null;
$color          = $body['color']          ?? '#E74C3C';
$from_object_id = isset($body['from_object_id']) ? (int)$body['from_object_id']   : null;
$to_object_id   = isset($body['to_object_id'])   ? (int)$body['to_object_id']     : null;

// Display ft = override if set, otherwise calculated
$display_ft = $override_ft ?? $calculated_ft;

if (!$project_id) {
    json_response(['success' => false, 'error' => 'project_id required'], 400);
}

$project = db_row('SELECT id FROM projects WHERE id = :id', [':id' => $project_id]);
if (!$project) {
    json_response(['success' => false, 'error' => 'Project not found'], 404);
}

if ($meas_id > 0) {
    db_execute(
        "UPDATE site_plan_measurements SET
           label=:label, from_lat=:from_lat, from_lng=:from_lng,
           to_lat=:to_lat, to_lng=:to_lng,
           from_x=:from_x, from_y=:from_y, to_x=:to_x, to_y=:to_y,
           geometry_type=:geometry_type,
           calculated_ft=:calculated_ft, override_ft=:override_ft,
           display_ft=:display_ft, color=:color,
           from_object_id=:from_object_id, to_object_id=:to_object_id
         WHERE id=:id AND project_id=:project_id",
        [
            ':label'          => $label,
            ':from_lat'       => $from_lat,
            ':from_lng'       => $from_lng,
            ':to_lat'         => $to_lat,
            ':to_lng'         => $to_lng,
            ':from_x'         => $from_x,
            ':from_y'         => $from_y,
            ':to_x'           => $to_x,
            ':to_y'           => $to_y,
            ':geometry_type'  => $geometry_type,
            ':calculated_ft'  => $calculated_ft,
            ':override_ft'    => $override_ft,
            ':display_ft'     => $display_ft,
            ':color'          => $color,
            ':from_object_id' => $from_object_id ?: null,
            ':to_object_id'   => $to_object_id   ?: null,
            ':id'             => $meas_id,
            ':project_id'     => $project_id,
        ]
    );
    json_response(['success' => true, 'id' => $meas_id, 'action' => 'updated', 'display_ft' => $display_ft]);

} else {
    $new_id = db_insert(
        "INSERT INTO site_plan_measurements
           (project_id, label, from_lat, from_lng, to_lat, to_lng,
            from_x, from_y, to_x, to_y, geometry_type,
            calculated_ft, override_ft, display_ft, color,
            from_object_id, to_object_id)
         VALUES
           (:project_id, :label, :from_lat, :from_lng, :to_lat, :to_lng,
            :from_x, :from_y, :to_x, :to_y, :geometry_type,
            :calculated_ft, :override_ft, :display_ft, :color,
            :from_object_id, :to_object_id)",
        [
            ':project_id'     => $project_id,
            ':label'          => $label,
            ':from_lat'       => $from_lat,
            ':from_lng'       => $from_lng,
            ':to_lat'         => $to_lat,
            ':to_lng'         => $to_lng,
            ':from_x'         => $from_x,
            ':from_y'         => $from_y,
            ':to_x'           => $to_x,
            ':to_y'           => $to_y,
            ':geometry_type'  => $geometry_type,
            ':calculated_ft'  => $calculated_ft,
            ':override_ft'    => $override_ft,
            ':display_ft'     => $display_ft,
            ':color'          => $color,
            ':from_object_id' => $from_object_id ?: null,
            ':to_object_id'   => $to_object_id   ?: null,
        ]
    );
    json_response(['success' => true, 'id' => $new_id, 'action' => 'created', 'display_ft' => $display_ft]);
}
