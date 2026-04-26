<?php
// API: Create or update a site plan object (structure, polygon, line, label, etc.)
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/helpers.php';
safe_session_start();
csrf_check();

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    json_response(['success' => false, 'error' => 'POST required'], 405);
}

$body = json_decode(file_get_contents('php://input'), true) ?? $_POST;

$project_id     = (int)($body['project_id']     ?? 0);
$object_id      = (int)($body['id']             ?? 0);
$layer          = $body['layer']          ?? 'structures';
$object_type    = $body['object_type']    ?? 'rect';
$structure_type = $body['structure_type'] ?? null;
$label          = $body['label']          ?? '';
$status         = $body['status']         ?? 'proposed';
$geometry_type  = $body['geometry_type']  ?? 'latlng';
$coordinates    = $body['coordinates']    ?? null;
$anchor_lat     = isset($body['anchor_lat'])    ? (float)$body['anchor_lat']    : null;
$anchor_lng     = isset($body['anchor_lng'])    ? (float)$body['anchor_lng']    : null;
$width_ft       = isset($body['width_ft'])      ? (float)$body['width_ft']      : null;
$length_ft      = isset($body['length_ft'])     ? (float)$body['length_ft']     : null;
$height_ft      = isset($body['height_ft'])     ? (float)$body['height_ft']     : null;
$rotation_deg   = isset($body['rotation_deg'])  ? (float)$body['rotation_deg']  : 0.0;
$fill_color     = $body['fill_color']     ?? '#4A90D9';
$fill_opacity   = isset($body['fill_opacity'])  ? (float)$body['fill_opacity']  : 0.40;
$border_color   = $body['border_color']   ?? '#1A5276';
$border_width   = isset($body['border_width'])  ? (int)$body['border_width']    : 2;
$font_size      = isset($body['font_size'])     ? (int)$body['font_size']       : 12;
$notes          = $body['notes']          ?? null;
$sort_order     = isset($body['sort_order'])    ? (int)$body['sort_order']      : 0;

// Auto-calculate sqft
$sqft = ($width_ft && $length_ft) ? round($width_ft * $length_ft, 2) : null;

// Encode coordinates as JSON string if passed as array
if (is_array($coordinates)) {
    $coordinates = json_encode($coordinates);
}

if (!$project_id) {
    json_response(['success' => false, 'error' => 'project_id required'], 400);
}

// Validate the project exists
$project = db_row('SELECT id FROM projects WHERE id = :id', [':id' => $project_id]);
if (!$project) {
    json_response(['success' => false, 'error' => 'Project not found'], 404);
}

$valid_layers   = ['structures','setbacks','utilities','driveways','drainage','labels','measurements','parcel'];
$valid_statuses = ['existing','proposed','removed'];
$valid_geom     = ['latlng','canvas'];

if (!in_array($layer,         $valid_layers,   true)) $layer         = 'structures';
if (!in_array($status,        $valid_statuses, true)) $status        = 'proposed';
if (!in_array($geometry_type, $valid_geom,     true)) $geometry_type = 'latlng';

if ($object_id > 0) {
    // Update existing object
    db_execute(
        "UPDATE site_plan_objects SET
           layer=:layer, object_type=:object_type, structure_type=:structure_type,
           label=:label, status=:status, geometry_type=:geometry_type,
           coordinates=:coordinates, anchor_lat=:anchor_lat, anchor_lng=:anchor_lng,
           width_ft=:width_ft, length_ft=:length_ft, height_ft=:height_ft,
           sqft=:sqft, rotation_deg=:rotation_deg,
           fill_color=:fill_color, fill_opacity=:fill_opacity,
           border_color=:border_color, border_width=:border_width,
           font_size=:font_size, notes=:notes, sort_order=:sort_order
         WHERE id=:id AND project_id=:project_id",
        [
            ':layer'          => $layer,
            ':object_type'    => $object_type,
            ':structure_type' => $structure_type,
            ':label'          => $label,
            ':status'         => $status,
            ':geometry_type'  => $geometry_type,
            ':coordinates'    => $coordinates,
            ':anchor_lat'     => $anchor_lat,
            ':anchor_lng'     => $anchor_lng,
            ':width_ft'       => $width_ft,
            ':length_ft'      => $length_ft,
            ':height_ft'      => $height_ft,
            ':sqft'           => $sqft,
            ':rotation_deg'   => $rotation_deg,
            ':fill_color'     => $fill_color,
            ':fill_opacity'   => $fill_opacity,
            ':border_color'   => $border_color,
            ':border_width'   => $border_width,
            ':font_size'      => $font_size,
            ':notes'          => $notes,
            ':sort_order'     => $sort_order,
            ':id'             => $object_id,
            ':project_id'     => $project_id,
        ]
    );
    json_response(['success' => true, 'id' => $object_id, 'action' => 'updated']);

} else {
    // Insert new object
    $new_id = db_insert(
        "INSERT INTO site_plan_objects
           (project_id, layer, object_type, structure_type,
            label, status, geometry_type, coordinates,
            anchor_lat, anchor_lng,
            width_ft, length_ft, height_ft, sqft, rotation_deg,
            fill_color, fill_opacity, border_color, border_width,
            font_size, notes, sort_order)
         VALUES
           (:project_id, :layer, :object_type, :structure_type,
            :label, :status, :geometry_type, :coordinates,
            :anchor_lat, :anchor_lng,
            :width_ft, :length_ft, :height_ft, :sqft, :rotation_deg,
            :fill_color, :fill_opacity, :border_color, :border_width,
            :font_size, :notes, :sort_order)",
        [
            ':project_id'     => $project_id,
            ':layer'          => $layer,
            ':object_type'    => $object_type,
            ':structure_type' => $structure_type,
            ':label'          => $label,
            ':status'         => $status,
            ':geometry_type'  => $geometry_type,
            ':coordinates'    => $coordinates,
            ':anchor_lat'     => $anchor_lat,
            ':anchor_lng'     => $anchor_lng,
            ':width_ft'       => $width_ft,
            ':length_ft'      => $length_ft,
            ':height_ft'      => $height_ft,
            ':sqft'           => $sqft,
            ':rotation_deg'   => $rotation_deg,
            ':fill_color'     => $fill_color,
            ':fill_opacity'   => $fill_opacity,
            ':border_color'   => $border_color,
            ':border_width'   => $border_width,
            ':font_size'      => $font_size,
            ':notes'          => $notes,
            ':sort_order'     => $sort_order,
        ]
    );
    json_response(['success' => true, 'id' => $new_id, 'action' => 'created', 'sqft' => $sqft]);
}
