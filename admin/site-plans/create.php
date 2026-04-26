<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
safe_session_start();

$page_title = 'New Site Plan Project';
$errors = [];
$form = [
    'client_name'      => '',
    'project_name'     => '',
    'site_address'     => '',
    'city'             => '',
    'state'            => 'WA',
    'zip'              => '',
    'parcel_number'    => '',
    'jurisdiction'     => 'Yakima County',
    'project_type'     => 'new_home',
    'status'           => 'active',
    'existing_summary' => '',
    'proposed_summary' => '',
    'notes'            => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_check();

    foreach (array_keys($form) as $key) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    // Validation
    if ($form['client_name'] === '')   $errors[] = 'Client name is required.';
    if ($form['project_name'] === '')  $errors[] = 'Project name is required.';
    if ($form['site_address'] === '')  $errors[] = 'Site address is required.';

    if (empty($errors)) {
        $id = db_insert(
            "INSERT INTO projects
               (client_name, project_name, site_address, city, state, zip,
                parcel_number, jurisdiction, project_type, status,
                existing_summary, proposed_summary, notes)
             VALUES
               (:client_name, :project_name, :site_address, :city, :state, :zip,
                :parcel_number, :jurisdiction, :project_type, :status,
                :existing_summary, :proposed_summary, :notes)",
            [
                ':client_name'      => $form['client_name'],
                ':project_name'     => $form['project_name'],
                ':site_address'     => $form['site_address'],
                ':city'             => $form['city'],
                ':state'            => $form['state'],
                ':zip'              => $form['zip'],
                ':parcel_number'    => $form['parcel_number'],
                ':jurisdiction'     => $form['jurisdiction'],
                ':project_type'     => $form['project_type'],
                ':status'           => $form['status'],
                ':existing_summary' => $form['existing_summary'],
                ':proposed_summary' => $form['proposed_summary'],
                ':notes'            => $form['notes'],
            ]
        );

        // Seed default map settings
        db_execute(
            "INSERT INTO map_settings (project_id, center_lat, center_lng, zoom_level, basemap)
             VALUES (:pid, 46.6021, -120.5059, 18, 'esri')",
            [':pid' => $id]
        );

        // Seed default setback rules
        db_execute(
            "INSERT INTO setback_rules (project_id, front_ft, rear_ft, side_ft, accessory_ft, well_sep_ft, septic_sep_ft)
             VALUES (:pid, 20, 5, 5, 5, 100, 100)",
            [':pid' => $id]
        );

        // Seed default layer visibility
        $layers = [
            ['satellite',    'Satellite Map',       1, 0],
            ['parcel',       'Parcel Boundary',      1, 1],
            ['existing',     'Existing Structures',  1, 2],
            ['proposed',     'Proposed Structures',  1, 3],
            ['setbacks',     'Setbacks',             1, 4],
            ['measurements', 'Measurements',         1, 5],
            ['utilities',    'Utilities',            1, 6],
            ['labels',       'Labels',               1, 7],
            ['driveways',    'Driveways / Access',   1, 8],
            ['drainage',     'Drainage',             1, 9],
            ['notes',        'Notes',                0, 10],
        ];
        foreach ($layers as [$key, $label, $vis, $sort]) {
            db_execute(
                "INSERT INTO site_plan_layers (project_id, layer_key, label, visible, sort_order)
                 VALUES (:pid, :key, :label, :vis, :sort)
                 ON DUPLICATE KEY UPDATE label=VALUES(label)",
                [':pid' => $id, ':key' => $key, ':label' => $label, ':vis' => $vis, ':sort' => $sort]
            );
        }

        flash_set('success', "Project '{$form['project_name']}' created. Open the editor to start drawing.");
        redirect(BASE_URL . '/admin/site-plans/edit.php?id=' . $id);
    }
}

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container py-4" style="max-width:860px;">
  <div class="d-flex align-items-center mb-4">
    <a href="index.php" class="btn btn-sm btn-outline-secondary me-3">
      <i class="bi bi-arrow-left"></i>
    </a>
    <h4 class="mb-0"><i class="bi bi-plus-circle text-primary me-2"></i>New Site Plan Project</h4>
  </div>

  <?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
      <ul class="mb-0">
        <?php foreach ($errors as $e): ?>
          <li><?= h($e) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>
  <?php endif; ?>

  <form method="post" id="createForm" novalidate>
    <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

    <!-- ── Project Info ── -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header fw-semibold">
        <i class="bi bi-person-fill me-2 text-primary"></i>Client & Project Info
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Client Name <span class="text-danger">*</span></label>
            <input type="text" name="client_name" class="form-control"
                   value="<?= h($form['client_name']) ?>" required>
          </div>
          <div class="col-md-6">
            <label class="form-label">Project Name <span class="text-danger">*</span></label>
            <input type="text" name="project_name" class="form-control"
                   value="<?= h($form['project_name']) ?>" required
                   placeholder="e.g. Smith ADU – Orchard View Rd">
          </div>
          <div class="col-md-4">
            <label class="form-label">Project Type</label>
            <select name="project_type" class="form-select">
              <?php foreach ([
                'new_home'=>'New Home','adu'=>'ADU','garage'=>'Garage',
                'shop'=>'Shop / Steel Building','remodel'=>'Remodel',
                'addition'=>'Addition','site_development'=>'Site Development','other'=>'Other'
              ] as $v => $l): ?>
                <option value="<?= $v ?>" <?= $form['project_type']===$v?'selected':'' ?>>
                  <?= $l ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Status</label>
            <select name="status" class="form-select">
              <?php foreach (['draft','active','submitted','approved','archived'] as $s): ?>
                <option value="<?= $s ?>" <?= $form['status']===$s?'selected':'' ?>>
                  <?= ucfirst($s) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="col-md-4">
            <label class="form-label">Jurisdiction</label>
            <input type="text" name="jurisdiction" class="form-control"
                   value="<?= h($form['jurisdiction']) ?>"
                   placeholder="e.g. Yakima County">
          </div>
        </div>
      </div>
    </div>

    <!-- ── Site Address ── -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header fw-semibold">
        <i class="bi bi-geo-alt-fill me-2 text-danger"></i>Site Address
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Street Address <span class="text-danger">*</span></label>
            <input type="text" name="site_address" class="form-control"
                   value="<?= h($form['site_address']) ?>" required
                   placeholder="1234 Orchard View Rd">
          </div>
          <div class="col-md-5">
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-control"
                   value="<?= h($form['city']) ?>" placeholder="Yakima">
          </div>
          <div class="col-md-3">
            <label class="form-label">State</label>
            <input type="text" name="state" class="form-control"
                   value="<?= h($form['state']) ?>" maxlength="2">
          </div>
          <div class="col-md-4">
            <label class="form-label">ZIP</label>
            <input type="text" name="zip" class="form-control"
                   value="<?= h($form['zip']) ?>">
          </div>
          <div class="col-md-6">
            <label class="form-label">Parcel Number / APN</label>
            <input type="text" name="parcel_number" class="form-control font-monospace"
                   value="<?= h($form['parcel_number']) ?>"
                   placeholder="191330-21003">
          </div>
        </div>
      </div>
    </div>

    <!-- ── Site Summary ── -->
    <div class="card mb-3 shadow-sm">
      <div class="card-header fw-semibold">
        <i class="bi bi-house-fill me-2 text-warning"></i>Site Summary
      </div>
      <div class="card-body">
        <div class="row g-3">
          <div class="col-md-6">
            <label class="form-label">Existing Structures Summary</label>
            <textarea name="existing_summary" class="form-control" rows="3"
                      placeholder="Describe existing structures on site…"><?= h($form['existing_summary']) ?></textarea>
          </div>
          <div class="col-md-6">
            <label class="form-label">Proposed Structures Summary</label>
            <textarea name="proposed_summary" class="form-control" rows="3"
                      placeholder="Describe proposed new structures…"><?= h($form['proposed_summary']) ?></textarea>
          </div>
          <div class="col-12">
            <label class="form-label">Project Notes</label>
            <textarea name="notes" class="form-control" rows="3"
                      placeholder="Permit notes, HOA info, special conditions…"><?= h($form['notes']) ?></textarea>
          </div>
        </div>
      </div>
    </div>

    <div class="d-flex gap-2">
      <button type="submit" class="btn btn-primary">
        <i class="bi bi-check-circle me-1"></i> Create Project & Open Editor
      </button>
      <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
    </div>
  </form>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
