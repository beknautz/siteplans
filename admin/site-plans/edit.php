<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
safe_session_start();

$id = (int)($_GET['id'] ?? 0);
if (!$id) {
    redirect(BASE_URL . '/admin/site-plans/index.php');
}

$project = db_row('SELECT * FROM projects WHERE id = :id', [':id' => $id]);
if (!$project) {
    flash_set('error', 'Project not found.');
    redirect(BASE_URL . '/admin/site-plans/index.php');
}

$map_settings = db_row(
    'SELECT * FROM map_settings WHERE project_id = :pid',
    [':pid' => $id]
) ?? [
    'center_lat' => 46.6021, 'center_lng' => -120.5059,
    'zoom_level' => 18, 'rotation' => 0, 'basemap' => 'esri',
    'calibration_px' => null, 'calibration_ft' => null, 'scale_factor' => null,
];

$setbacks = db_row(
    'SELECT * FROM setback_rules WHERE project_id = :pid',
    [':pid' => $id]
) ?? [
    'front_ft' => 20, 'rear_ft' => 5, 'side_ft' => 5,
    'accessory_ft' => 5, 'well_sep_ft' => 100, 'septic_sep_ft' => 100,
];

$layers = db_rows(
    'SELECT * FROM site_plan_layers WHERE project_id = :pid ORDER BY sort_order',
    [':pid' => $id]
);

$objects = db_rows(
    'SELECT * FROM site_plan_objects WHERE project_id = :pid AND visible = 1 ORDER BY sort_order, id',
    [':pid' => $id]
);

$measurements = db_rows(
    'SELECT * FROM site_plan_measurements WHERE project_id = :pid AND visible = 1',
    [':pid' => $id]
);

$parcel = db_row(
    'SELECT * FROM parcel_boundaries WHERE project_id = :pid ORDER BY id DESC LIMIT 1',
    [':pid' => $id]
);

$page_title = h($project['project_name']) . ' — Editor';
$body_class = 'editor-layout';

// Pass PHP data to JS
$js_data = json_encode([
    'projectId'    => $id,
    'project'      => $project,
    'mapSettings'  => $map_settings,
    'setbacks'     => $setbacks,
    'layers'       => $layers,
    'objects'      => $objects,
    'measurements' => $measurements,
    'parcel'       => $parcel,
    'baseUrl'      => BASE_URL,
    'mapboxToken'  => MAPBOX_TOKEN,
    'googleKey'    => GOOGLE_MAPS_KEY,
], JSON_HEX_TAG | JSON_HEX_APOS);

$extra_scripts = <<<HTML
<script>
  const SITE_PLAN_DATA = $js_data;
</script>
<script src="<?= BASE_URL ?>/assets/js/map-init.js"></script>
<script src="<?= BASE_URL ?>/assets/js/layer-manager.js"></script>
<script src="<?= BASE_URL ?>/assets/js/drawing-tools.js"></script>
<script src="<?= BASE_URL ?>/assets/js/structure-tools.js"></script>
<script src="<?= BASE_URL ?>/assets/js/measurement-tools.js"></script>
<script src="<?= BASE_URL ?>/assets/js/export-preview.js"></script>
HTML;

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<!-- ══════════════════════════════════════════════════════════
     EDITOR LAYOUT
     Left sidebar | Map canvas | Right panel
═══════════════════════════════════════════════════════════ -->

<!-- Top Toolbar -->
<div id="editorToolbar" class="editor-toolbar d-flex align-items-center gap-2 px-3 py-2 bg-dark border-bottom border-secondary">

  <!-- Project info -->
  <div class="me-3">
    <a href="index.php" class="btn btn-sm btn-outline-secondary" title="Back to projects">
      <i class="bi bi-arrow-left"></i>
    </a>
  </div>
  <div class="text-light me-3">
    <span class="fw-semibold"><?= h($project['project_name']) ?></span>
    <small class="text-secondary ms-2"><?= h($project['site_address']) ?></small>
  </div>

  <div class="vr mx-1 bg-secondary"></div>

  <!-- Tool buttons -->
  <div class="btn-group btn-group-sm" role="group" id="toolGroup">
    <button class="btn btn-outline-light tool-btn active" data-tool="select" title="Select / Move (V)">
      <i class="bi bi-cursor-fill"></i>
    </button>
    <button class="btn btn-outline-light tool-btn" data-tool="pan" title="Pan Map (H)">
      <i class="bi bi-hand-index-fill"></i>
    </button>
    <button class="btn btn-outline-warning tool-btn" data-tool="structure" title="Place Structure (S)">
      <i class="bi bi-building-fill"></i>
    </button>
    <button class="btn btn-outline-info tool-btn" data-tool="measure" title="Measure Distance (M)">
      <i class="bi bi-rulers"></i>
    </button>
    <button class="btn btn-outline-success tool-btn" data-tool="polygon" title="Draw Polygon (P)">
      <i class="bi bi-pentagon-fill"></i>
    </button>
    <button class="btn btn-outline-primary tool-btn" data-tool="line" title="Draw Line (L)">
      <i class="bi bi-slash-lg"></i>
    </button>
    <button class="btn btn-outline-light tool-btn" data-tool="text" title="Add Text Label (T)">
      <i class="bi bi-fonts"></i>
    </button>
    <button class="btn btn-outline-danger tool-btn" data-tool="setback" title="Draw Setback Line">
      <i class="bi bi-border-outer"></i>
    </button>
    <button class="btn btn-outline-secondary tool-btn" data-tool="parcel" title="Draw Parcel Boundary">
      <i class="bi bi-bounding-box"></i>
    </button>
  </div>

  <div class="vr mx-1 bg-secondary"></div>

  <!-- Calibration -->
  <button class="btn btn-sm btn-outline-warning" id="calibrateBtn" title="Calibrate Map Scale">
    <i class="bi bi-speedometer2 me-1"></i>Calibrate
  </button>

  <div class="vr mx-1 bg-secondary"></div>

  <!-- Zoom -->
  <button class="btn btn-sm btn-outline-light" id="zoomInBtn" title="Zoom In (+)">
    <i class="bi bi-plus-lg"></i>
  </button>
  <button class="btn btn-sm btn-outline-light" id="zoomOutBtn" title="Zoom Out (-)">
    <i class="bi bi-dash-lg"></i>
  </button>
  <button class="btn btn-sm btn-outline-light" id="fitBoundsBtn" title="Fit parcel to screen">
    <i class="bi bi-fullscreen"></i>
  </button>

  <div class="ms-auto d-flex gap-2">
    <!-- Save -->
    <button class="btn btn-sm btn-success" id="saveAllBtn">
      <i class="bi bi-floppy2-fill me-1"></i>Save
    </button>
    <!-- Export -->
    <a href="export.php?id=<?= $id ?>" class="btn btn-sm btn-primary" target="_blank">
      <i class="bi bi-file-earmark-pdf-fill me-1"></i>Export PDF
    </a>
    <!-- Status indicator -->
    <span id="saveStatus" class="small text-secondary align-self-center"></span>
  </div>
</div>

<div class="editor-body d-flex" style="height:calc(100vh - 112px);">

  <!-- ══ LEFT SIDEBAR ══ -->
  <div id="leftSidebar" class="editor-sidebar-left bg-dark text-light border-end border-secondary d-flex flex-column"
       style="width:260px;min-width:220px;overflow-y:auto;">

    <!-- Basemap selector -->
    <div class="p-2 border-bottom border-secondary">
      <label class="form-label small text-secondary mb-1">Basemap</label>
      <select id="basemapSelect" class="form-select form-select-sm bg-dark text-light border-secondary">
        <option value="esri"       <?= $map_settings['basemap']==='esri'?'selected':'' ?>>ESRI World Imagery</option>
        <option value="osm"        <?= $map_settings['basemap']==='osm'?'selected':'' ?>>OpenStreetMap</option>
        <option value="mapbox"     <?= $map_settings['basemap']==='mapbox'?'selected':'' ?>>Mapbox Satellite</option>
        <option value="google"     <?= $map_settings['basemap']==='google'?'selected':'' ?>>Google Maps (API key)</option>
      </select>
    </div>

    <!-- Address geocode -->
    <div class="p-2 border-bottom border-secondary">
      <label class="form-label small text-secondary mb-1">Jump to Address</label>
      <div class="input-group input-group-sm">
        <input type="text" id="geocodeInput" class="form-control bg-dark text-light border-secondary"
               placeholder="Address or parcel…"
               value="<?= h($project['site_address'] . ', ' . $project['city'] . ', ' . $project['state']) ?>">
        <button class="btn btn-outline-warning btn-sm" id="geocodeBtn">
          <i class="bi bi-geo-alt-fill"></i>
        </button>
      </div>
    </div>

    <!-- Layers Panel -->
    <div class="p-2 border-bottom border-secondary">
      <div class="d-flex align-items-center justify-content-between mb-1">
        <span class="small fw-semibold text-secondary">LAYERS</span>
        <div>
          <button class="btn btn-outline-secondary btn-sm py-0 px-1 me-1" id="showAllLayers" title="Show All">
            <i class="bi bi-eye-fill"></i>
          </button>
          <button class="btn btn-outline-secondary btn-sm py-0 px-1" id="hideAllLayers" title="Hide All">
            <i class="bi bi-eye-slash-fill"></i>
          </button>
        </div>
      </div>
      <div id="layersList">
        <?php foreach ($layers as $layer): ?>
          <div class="layer-row d-flex align-items-center gap-2 py-1" data-layer="<?= h($layer['layer_key']) ?>">
            <div class="form-check form-switch mb-0">
              <input class="form-check-input layer-toggle" type="checkbox"
                     id="layer_<?= h($layer['layer_key']) ?>"
                     data-layer="<?= h($layer['layer_key']) ?>"
                     <?= $layer['visible'] ? 'checked' : '' ?>>
            </div>
            <label class="form-check-label small cursor-pointer flex-fill"
                   for="layer_<?= h($layer['layer_key']) ?>">
              <?= h($layer['label']) ?>
            </label>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Structure Quick Add -->
    <div class="p-2 border-bottom border-secondary">
      <div class="small fw-semibold text-secondary mb-2">QUICK ADD STRUCTURE</div>
      <div class="d-flex flex-wrap gap-1">
        <?php
        $quickStructures = [
          ['house',       'House',     'bi-house-fill',        '#AED6F1', 'existing'],
          ['adu',         'ADU',       'bi-house-door-fill',   '#A9DFBF', 'proposed'],
          ['garage',      'Garage',    'bi-car-front-fill',    '#D5D8DC', 'existing'],
          ['shop',        'Shop',      'bi-building-fill',     '#FAD7A0', 'proposed'],
          ['shed',        'Shed',      'bi-box-fill',          '#D2B4DE', 'existing'],
          ['deck',        'Deck',      'bi-grid-fill',         '#FDEBD0', 'proposed'],
          ['well',        'Well',      'bi-droplet-fill',      '#85C1E9', 'existing'],
          ['septic',      'Septic',    'bi-filter-circle-fill','#A9CCE3', 'existing'],
          ['driveway',    'Driveway',  'bi-sign-turn-right-fill','#CCD1D1','existing'],
          ['meter',       'Meter',     'bi-lightning-fill',    '#F9E79F', 'existing'],
        ];
        foreach ($quickStructures as [$type, $label, $icon, $color, $status]):
        ?>
          <button class="btn btn-sm btn-outline-secondary py-0 px-2 quick-struct"
                  data-type="<?= $type ?>" data-label="<?= $label ?>"
                  data-color="<?= $color ?>" data-status="<?= $status ?>"
                  title="Add <?= $label ?>">
            <i class="bi <?= $icon ?>" style="color:<?= $color ?>"></i>
            <span class="d-block" style="font-size:.65rem;"><?= $label ?></span>
          </button>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Setback Rules -->
    <div class="p-2 border-bottom border-secondary">
      <div class="d-flex align-items-center justify-content-between mb-1">
        <span class="small fw-semibold text-secondary">SETBACK RULES (ft)</span>
        <button class="btn btn-outline-secondary btn-sm py-0 px-1" id="applySetbacksBtn" title="Draw setbacks on map">
          <i class="bi bi-border-outer"></i>
        </button>
      </div>
      <div id="setbackForm"
           hx-put="api/save-setbacks.php?id=<?= $id ?>"
           hx-trigger="change delay:600ms"
           hx-swap="none">
        <div class="row g-1">
          <div class="col-6"><label class="form-label mb-0" style="font-size:.7rem;">Front</label>
            <input type="number" name="front_ft" class="form-control form-control-sm bg-dark text-light border-secondary"
                   value="<?= (float)$setbacks['front_ft'] ?>" step="0.5"></div>
          <div class="col-6"><label class="form-label mb-0" style="font-size:.7rem;">Rear</label>
            <input type="number" name="rear_ft" class="form-control form-control-sm bg-dark text-light border-secondary"
                   value="<?= (float)$setbacks['rear_ft'] ?>" step="0.5"></div>
          <div class="col-6"><label class="form-label mb-0" style="font-size:.7rem;">Side</label>
            <input type="number" name="side_ft" class="form-control form-control-sm bg-dark text-light border-secondary"
                   value="<?= (float)$setbacks['side_ft'] ?>" step="0.5"></div>
          <div class="col-6"><label class="form-label mb-0" style="font-size:.7rem;">Accessory</label>
            <input type="number" name="accessory_ft" class="form-control form-control-sm bg-dark text-light border-secondary"
                   value="<?= (float)$setbacks['accessory_ft'] ?>" step="0.5"></div>
          <div class="col-6"><label class="form-label mb-0" style="font-size:.7rem;">Well Sep.</label>
            <input type="number" name="well_sep_ft" class="form-control form-control-sm bg-dark text-light border-secondary"
                   value="<?= (float)$setbacks['well_sep_ft'] ?>" step="1"></div>
          <div class="col-6"><label class="form-label mb-0" style="font-size:.7rem;">Septic Sep.</label>
            <input type="number" name="septic_sep_ft" class="form-control form-control-sm bg-dark text-light border-secondary"
                   value="<?= (float)$setbacks['septic_sep_ft'] ?>" step="1"></div>
        </div>
        <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">
      </div>
    </div>

    <!-- Scale calibration info -->
    <div class="p-2 border-bottom border-secondary">
      <div class="small fw-semibold text-secondary mb-1">SCALE CALIBRATION</div>
      <div class="small text-secondary" id="calibrationInfo">
        <?php if (!empty($map_settings['scale_factor'])): ?>
          <span class="text-success">
            <?= number_format((float)$map_settings['scale_factor'], 4) ?> ft/px
          </span>
        <?php else: ?>
          <span class="text-warning">Not calibrated — click Calibrate</span>
        <?php endif; ?>
      </div>
    </div>

    <!-- Object count summary -->
    <div class="p-2 mt-auto">
      <small class="text-secondary">
        <?= count($objects) ?> object(s) &middot; <?= count($measurements) ?> measurement(s)
      </small>
    </div>
  </div>

  <!-- ══ MAP CANVAS ══ -->
  <div id="mapContainer" class="flex-fill position-relative">
    <div id="map" style="width:100%;height:100%;"></div>

    <!-- North Arrow overlay -->
    <div id="northArrow" class="position-absolute" style="bottom:60px;right:10px;z-index:1000;pointer-events:none;">
      <div class="north-arrow-widget text-center bg-white bg-opacity-75 rounded p-1" style="width:36px;">
        <i class="bi bi-arrow-up fs-5" style="color:#111;"></i>
        <div style="font-size:.6rem;font-weight:bold;color:#111;">N</div>
      </div>
    </div>

    <!-- Scale bar is provided by Leaflet -->

    <!-- Calibration overlay (hidden by default) -->
    <div id="calibrationPanel" class="position-absolute start-50 translate-middle-x"
         style="top:70px;z-index:1500;display:none;">
      <div class="card shadow-lg border-warning" style="min-width:360px;">
        <div class="card-header bg-warning text-dark fw-semibold">
          <i class="bi bi-speedometer2 me-2"></i>Scale Calibration
        </div>
        <div class="card-body">
          <p class="small mb-2">
            Draw a line on the map between two points of known distance,
            then enter the real-world distance below.
          </p>
          <div class="mb-2">
            <button id="drawCalibrationLine" class="btn btn-warning btn-sm w-100">
              <i class="bi bi-pencil me-1"></i>Draw Calibration Line
            </button>
          </div>
          <div id="calibrationStatus" class="small text-secondary mb-2"></div>
          <div class="input-group input-group-sm mb-2">
            <span class="input-group-text">Known distance</span>
            <input type="number" id="calibrationFt" class="form-control"
                   placeholder="e.g. 50" step="0.1" min="1">
            <span class="input-group-text">ft</span>
          </div>
          <div class="d-flex gap-2">
            <button id="applyCalibration" class="btn btn-warning btn-sm flex-fill">
              Apply Calibration
            </button>
            <button id="cancelCalibration" class="btn btn-outline-secondary btn-sm">
              Cancel
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Status toast -->
    <div id="mapToast" class="position-absolute bottom-0 start-50 translate-middle-x mb-3"
         style="z-index:2000;display:none;">
      <div class="toast show bg-dark text-light border-0 shadow">
        <div class="toast-body small" id="mapToastMsg"></div>
      </div>
    </div>
  </div>

  <!-- ══ RIGHT PROPERTIES PANEL ══ -->
  <div id="rightPanel" class="editor-sidebar-right bg-dark text-light border-start border-secondary"
       style="width:280px;min-width:240px;overflow-y:auto;display:flex;flex-direction:column;">

    <!-- Object Properties (shown when object selected) -->
    <div id="objPropertiesPanel" class="p-2">
      <div class="small fw-semibold text-secondary mb-2">PROPERTIES</div>
      <div id="noObjectSelected" class="text-secondary small text-center py-4">
        <i class="bi bi-cursor-fill d-block fs-3 mb-2"></i>
        Click an object on the map to edit its properties
      </div>
      <div id="objectProperties" style="display:none;">
        <input type="hidden" id="objId">

        <div class="mb-2">
          <label class="form-label small mb-0">Label</label>
          <input type="text" id="objLabel" class="form-control form-control-sm bg-dark text-light border-secondary">
        </div>
        <div class="mb-2">
          <label class="form-label small mb-0">Type</label>
          <select id="objStructureType" class="form-select form-select-sm bg-dark text-light border-secondary">
            <option value="">— None —</option>
            <?php
            $structTypes = ['house'=>'House','adu'=>'ADU','garage'=>'Garage','shop'=>'Shop',
              'shed'=>'Shed','deck'=>'Deck','patio'=>'Patio','driveway'=>'Driveway',
              'carport'=>'Carport','well'=>'Well','septic'=>'Septic Tank',
              'drain_field'=>'Drain Field','meter'=>'Utility Meter',
              'water_line'=>'Water Line','sewer_line'=>'Sewer Line','power_line'=>'Power Line'];
            foreach ($structTypes as $v => $l):
            ?>
              <option value="<?= $v ?>"><?= $l ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-2">
          <label class="form-label small mb-0">Status</label>
          <select id="objStatus" class="form-select form-select-sm bg-dark text-light border-secondary">
            <option value="existing">Existing</option>
            <option value="proposed">Proposed</option>
            <option value="removed">Removed</option>
          </select>
        </div>

        <div class="row g-1 mb-2">
          <div class="col-6">
            <label class="form-label small mb-0">Width (ft)</label>
            <input type="number" id="objWidth" step="0.5"
                   class="form-control form-control-sm bg-dark text-light border-secondary">
          </div>
          <div class="col-6">
            <label class="form-label small mb-0">Length (ft)</label>
            <input type="number" id="objLength" step="0.5"
                   class="form-control form-control-sm bg-dark text-light border-secondary">
          </div>
          <div class="col-6">
            <label class="form-label small mb-0">Height (ft)</label>
            <input type="number" id="objHeight" step="0.5"
                   class="form-control form-control-sm bg-dark text-light border-secondary">
          </div>
          <div class="col-6">
            <label class="form-label small mb-0">Sq Ft</label>
            <input type="number" id="objSqft" readonly
                   class="form-control form-control-sm bg-dark text-secondary border-secondary">
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label small mb-0">Rotation (°)</label>
          <input type="number" id="objRotation" step="1" min="-360" max="360"
                 class="form-control form-control-sm bg-dark text-light border-secondary">
        </div>

        <div class="row g-1 mb-2">
          <div class="col-6">
            <label class="form-label small mb-0">Fill Color</label>
            <input type="color" id="objFillColor" class="form-control form-control-sm form-control-color w-100">
          </div>
          <div class="col-6">
            <label class="form-label small mb-0">Border Color</label>
            <input type="color" id="objBorderColor" class="form-control form-control-sm form-control-color w-100">
          </div>
          <div class="col-12">
            <label class="form-label small mb-0">Fill Opacity</label>
            <input type="range" id="objFillOpacity" min="0" max="1" step="0.05"
                   class="form-range">
            <small class="text-secondary" id="objFillOpacityVal">0.4</small>
          </div>
        </div>

        <div class="mb-2">
          <label class="form-label small mb-0">Notes</label>
          <textarea id="objNotes" class="form-control form-control-sm bg-dark text-light border-secondary" rows="2"></textarea>
        </div>

        <div class="d-flex gap-1">
          <button id="saveObjectBtn" class="btn btn-success btn-sm flex-fill">
            <i class="bi bi-floppy2 me-1"></i>Save
          </button>
          <button id="deleteObjectBtn" class="btn btn-outline-danger btn-sm">
            <i class="bi bi-trash3"></i>
          </button>
          <button id="deselectObjectBtn" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-x-lg"></i>
          </button>
        </div>
      </div>
    </div>

    <!-- Measurement Properties -->
    <div id="measurementPanel" class="p-2 border-top border-secondary" style="display:none;">
      <div class="small fw-semibold text-secondary mb-2">MEASUREMENT</div>
      <input type="hidden" id="measId">
      <div class="mb-2">
        <label class="form-label small mb-0">Label</label>
        <input type="text" id="measLabel" class="form-control form-control-sm bg-dark text-light border-secondary">
      </div>
      <div class="row g-1 mb-2">
        <div class="col-6">
          <label class="form-label small mb-0">Calculated (ft)</label>
          <input type="number" id="measCalcFt" readonly step="0.1"
                 class="form-control form-control-sm bg-dark text-secondary border-secondary">
        </div>
        <div class="col-6">
          <label class="form-label small mb-0">Override (ft)</label>
          <input type="number" id="measOverrideFt" step="0.1" min="0"
                 class="form-control form-control-sm bg-dark text-light border-secondary"
                 placeholder="Optional">
        </div>
      </div>
      <div class="mb-2">
        <label class="form-label small mb-0">Color</label>
        <input type="color" id="measColor" class="form-control form-control-sm form-control-color w-100" value="#E74C3C">
      </div>
      <div class="d-flex gap-1">
        <button id="saveMeasurementBtn" class="btn btn-success btn-sm flex-fill">
          <i class="bi bi-floppy2 me-1"></i>Save
        </button>
        <button id="deleteMeasurementBtn" class="btn btn-outline-danger btn-sm">
          <i class="bi bi-trash3"></i>
        </button>
      </div>
    </div>

    <!-- Project Summary (collapsed by default) -->
    <div class="p-2 border-top border-secondary mt-auto">
      <div class="small fw-semibold text-secondary mb-1">PROJECT SUMMARY</div>
      <div class="small text-secondary">
        <div><i class="bi bi-person-fill me-1"></i><?= h($project['client_name']) ?></div>
        <div><i class="bi bi-geo-alt me-1"></i><?= h($project['site_address']) ?></div>
        <div><i class="bi bi-hash me-1"></i><?= h($project['parcel_number'] ?: '—') ?></div>
        <div><i class="bi bi-buildings me-1"></i><?= h($project['jurisdiction']) ?></div>
        <div class="mt-1"><?= status_badge($project['status']) ?>
          <span class="ms-1"><?= project_type_label($project['project_type']) ?></span>
        </div>
      </div>
      <a href="create.php?clone=<?= $id ?>" class="btn btn-outline-secondary btn-sm mt-2 w-100">
        <i class="bi bi-pencil-square me-1"></i>Edit Project Info
      </a>
    </div>
  </div>

</div><!-- /.editor-body -->

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
