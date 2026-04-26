<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
safe_session_start();

$id = (int)($_GET['id'] ?? 0);
if (!$id) redirect(BASE_URL . '/admin/site-plans/index.php');

$project = db_row('SELECT * FROM projects WHERE id = :id', [':id' => $id]);
if (!$project) {
    flash_set('error', 'Project not found.');
    redirect(BASE_URL . '/admin/site-plans/index.php');
}

$map_settings = db_row('SELECT * FROM map_settings WHERE project_id=:pid', [':pid' => $id]);
$setbacks     = db_row('SELECT * FROM setback_rules WHERE project_id=:pid', [':pid' => $id])
                  ?? ['front_ft'=>20,'rear_ft'=>5,'side_ft'=>5,'accessory_ft'=>5,'well_sep_ft'=>100,'septic_sep_ft'=>100];
$objects      = db_rows('SELECT * FROM site_plan_objects WHERE project_id=:pid AND visible=1 ORDER BY sort_order,id', [':pid' => $id]);
$measurements = db_rows('SELECT * FROM site_plan_measurements WHERE project_id=:pid AND visible=1', [':pid' => $id]);
$exports      = db_rows('SELECT * FROM permit_exports WHERE project_id=:pid ORDER BY created_at DESC LIMIT 10', [':pid' => $id]);

$page_title  = 'Export — ' . h($project['project_name']);
$body_class  = '';

$now = date('F j, Y');

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>

<div class="container-fluid py-4">
  <div class="d-flex align-items-center mb-4 gap-3">
    <a href="edit.php?id=<?= $id ?>" class="btn btn-outline-secondary btn-sm">
      <i class="bi bi-arrow-left"></i> Back to Editor
    </a>
    <h4 class="mb-0"><i class="bi bi-file-earmark-pdf-fill text-danger me-2"></i>Export Site Plan</h4>
  </div>

  <?= flash_html() ?>

  <div class="row g-4">
    <!-- Export options column -->
    <div class="col-lg-4">
      <div class="card shadow-sm mb-3">
        <div class="card-header fw-semibold"><i class="bi bi-sliders me-2"></i>Export Options</div>
        <div class="card-body">
          <form id="exportForm" method="post" action="api/export-pdf.php">
            <input type="hidden" name="project_id" value="<?= $id ?>">
            <input type="hidden" name="csrf_token" value="<?= csrf_token() ?>">

            <div class="mb-3">
              <label class="form-label">Paper Size</label>
              <select name="paper_size" class="form-select">
                <option value="letter">8.5 × 11 (Letter)</option>
                <option value="ledger" selected>11 × 17 (Ledger / Tabloid)</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Orientation</label>
              <select name="orientation" class="form-select">
                <option value="landscape" selected>Landscape</option>
                <option value="portrait">Portrait</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Export Type</label>
              <select name="export_type" class="form-select">
                <option value="pdf">PDF (server-generated)</option>
                <option value="print">Browser Print-to-PDF</option>
              </select>
            </div>
            <div class="mb-3">
              <label class="form-label">Notes to include</label>
              <textarea name="notes" class="form-control" rows="2"
                        placeholder="Optional export notes…"><?= h($project['notes']) ?></textarea>
            </div>

            <div class="d-grid gap-2">
              <button type="submit" name="action" value="pdf" class="btn btn-danger">
                <i class="bi bi-file-earmark-pdf me-1"></i> Generate PDF
              </button>
              <button type="button" class="btn btn-outline-primary" onclick="window.SitePlanExport?.printPermitSheet()">
                <i class="bi bi-printer me-1"></i> Browser Print Preview
              </button>
            </div>
          </form>
        </div>
      </div>

      <!-- Export history -->
      <?php if ($exports): ?>
      <div class="card shadow-sm">
        <div class="card-header fw-semibold"><i class="bi bi-clock-history me-2"></i>Recent Exports</div>
        <ul class="list-group list-group-flush">
          <?php foreach ($exports as $exp): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center small">
              <div>
                <i class="bi bi-file-earmark-pdf text-danger me-1"></i>
                <?= h(strtoupper($exp['paper_size'])) ?> &middot; <?= h($exp['orientation']) ?>
              </div>
              <span class="text-secondary"><?= date('m/d/Y g:i a', strtotime($exp['created_at'])) ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>
      <?php endif; ?>
    </div>

    <!-- Permit sheet preview column -->
    <div class="col-lg-8">
      <div class="card shadow-sm">
        <div class="card-header fw-semibold d-flex justify-content-between align-items-center">
          <span><i class="bi bi-eye me-2"></i>Permit Sheet Preview</span>
          <button class="btn btn-sm btn-outline-secondary" onclick="window.print()">
            <i class="bi bi-printer me-1"></i> Print This
          </button>
        </div>
        <div class="card-body p-3">
          <?php
          $existing_objs = array_filter($objects, fn($o) => $o['status'] === 'existing');
          $proposed_objs = array_filter($objects, fn($o) => $o['status'] === 'proposed');
          ?>

          <!-- ── Permit Sheet ── -->
          <div class="export-preview border rounded" style="font-size:9pt;font-family:Arial,sans-serif;">

            <!-- Header -->
            <div class="sheet-header d-flex justify-content-between align-items-start pb-2 mb-2"
                 style="border-bottom:3px solid #1A5276;">
              <div>
                <div style="font-size:14pt;font-weight:700;color:#1A5276;">BaceBuilt LLC — Permit Site Plan</div>
                <div><strong>Project:</strong> <?= h($project['project_name']) ?>
                     &nbsp;|&nbsp; <strong>Client:</strong> <?= h($project['client_name']) ?>
                     &nbsp;|&nbsp; <strong>Date:</strong> <?= $now ?></div>
                <div><strong>Address:</strong> <?= h($project['site_address']) ?>, <?= h($project['city']) ?>, <?= h($project['state']) ?> <?= h($project['zip']) ?>
                     &nbsp;|&nbsp; <strong>Parcel:</strong> <?= h($project['parcel_number'] ?: '—') ?>
                     &nbsp;|&nbsp; <strong>Jurisdiction:</strong> <?= h($project['jurisdiction']) ?></div>
              </div>
              <div class="text-end" style="min-width:120px;">
                <div style="font-weight:700;color:#1A5276;">BaceBuilt LLC</div>
                <div>Site Plan Tool</div>
                <div>Scale: Per calibration</div>
                <div>North: ↑</div>
              </div>
            </div>

            <!-- Main body: map placeholder + sidebar -->
            <div class="d-flex gap-3">

              <!-- Map area -->
              <div style="flex:1;border:2px solid #1A5276;min-height:360px;background:#dce8f0;
                           display:flex;align-items:center;justify-content:center;position:relative;border-radius:4px;">
                <div class="text-center text-secondary" style="padding:20px;">
                  <div style="font-size:36pt;opacity:.2;">🗺</div>
                  <div><strong>Satellite / Site Plan Image</strong></div>
                  <div style="font-size:8pt;margin-top:8px;">
                    Open the map editor and use <em>Browser Print → Save as PDF</em><br>
                    to capture the live satellite + drawing overlay.
                  </div>
                </div>
                <div style="position:absolute;top:8px;right:8px;background:rgba(255,255,255,.85);
                             border:1px solid #999;border-radius:3px;padding:3px 6px;font-size:8pt;
                             font-weight:bold;text-align:center;line-height:1.3;">↑<br>N</div>
              </div>

              <!-- Sidebar -->
              <div style="width:220px;flex-shrink:0;display:flex;flex-direction:column;gap:8px;">

                <!-- Legend -->
                <div style="border:1px solid #ccc;border-radius:3px;overflow:hidden;">
                  <div style="background:#1A5276;color:#fff;padding:3px 8px;font-weight:700;font-size:8.5pt;">Legend</div>
                  <div style="padding:6px 8px;">
                    <?php
                    $legend = [
                      ['#AED6F1','#1A5276','solid',  'Existing House'],
                      ['#A9DFBF','#1E8449','dashed', 'Proposed Structure'],
                      ['#D5D8DC','#566573','solid',  'Garage'],
                      ['transparent','#8E44AD','dashed','Parcel Boundary'],
                      ['transparent','#E74C3C','dashed','Setback Lines'],
                      ['transparent','#E74C3C','solid', 'Measurements'],
                      ['#CCD1D1','#717D7E','solid',  'Driveway'],
                      ['#85C1E9','#1A5276','solid',  'Well / Water'],
                      ['#A9CCE3','#1A5276','solid',  'Septic'],
                    ];
                    foreach ($legend as [$fill, $brd, $dash, $label]):
                    ?>
                      <div style="display:flex;align-items:center;gap:6px;margin:2px 0;">
                        <div style="width:16px;height:11px;background:<?= h($fill) ?>;border:1px <?= $dash ?> <?= h($brd) ?>;border-radius:1px;flex-shrink:0;"></div>
                        <span><?= h($label) ?></span>
                      </div>
                    <?php endforeach; ?>
                  </div>
                </div>

                <!-- Setbacks -->
                <div style="border:1px solid #ccc;border-radius:3px;overflow:hidden;">
                  <div style="background:#1A5276;color:#fff;padding:3px 8px;font-weight:700;font-size:8.5pt;">Setback Requirements</div>
                  <div style="padding:4px 8px;">
                    <table style="width:100%;border-collapse:collapse;font-size:8pt;">
                      <?php foreach ([
                        ['Front',     $setbacks['front_ft']],
                        ['Rear',      $setbacks['rear_ft']],
                        ['Side',      $setbacks['side_ft']],
                        ['Accessory', $setbacks['accessory_ft']],
                        ['Well Sep.', $setbacks['well_sep_ft']],
                        ['Septic Sep.',$setbacks['septic_sep_ft']],
                      ] as [$type, $dist]): ?>
                        <tr style="border-bottom:1px solid #eee;">
                          <td style="padding:2px 4px;"><?= h($type) ?></td>
                          <td style="padding:2px 4px;font-weight:600;"><?= number_format((float)$dist, 1) ?> ft</td>
                        </tr>
                      <?php endforeach; ?>
                    </table>
                  </div>
                </div>

                <!-- Structures -->
                <?php if ($objects): ?>
                <div style="border:1px solid #ccc;border-radius:3px;overflow:hidden;">
                  <div style="background:#1A5276;color:#fff;padding:3px 8px;font-weight:700;font-size:8.5pt;">Structures</div>
                  <div style="padding:4px 8px;">
                    <table style="width:100%;border-collapse:collapse;font-size:7.5pt;">
                      <thead>
                        <tr style="background:#2E4057;color:#fff;">
                          <th style="padding:2px 4px;">Label</th>
                          <th style="padding:2px 4px;">Status</th>
                          <th style="padding:2px 4px;">SF</th>
                        </tr>
                      </thead>
                      <tbody>
                      <?php foreach ($objects as $obj): ?>
                        <tr style="border-bottom:1px solid #eee;">
                          <td style="padding:2px 4px;"><?= h($obj['label'] ?: $obj['structure_type']) ?></td>
                          <td style="padding:2px 4px;"><?= h(ucfirst($obj['status'])) ?></td>
                          <td style="padding:2px 4px;"><?= $obj['sqft'] ? number_format((float)$obj['sqft']) : '—' ?></td>
                        </tr>
                      <?php endforeach; ?>
                      </tbody>
                    </table>
                  </div>
                </div>
                <?php endif; ?>

                <!-- Measurements -->
                <?php if ($measurements): ?>
                <div style="border:1px solid #ccc;border-radius:3px;overflow:hidden;">
                  <div style="background:#1A5276;color:#fff;padding:3px 8px;font-weight:700;font-size:8.5pt;">Measurements</div>
                  <div style="padding:4px 8px;">
                    <table style="width:100%;border-collapse:collapse;font-size:7.5pt;">
                      <?php foreach ($measurements as $m): ?>
                        <tr style="border-bottom:1px solid #eee;">
                          <td style="padding:2px 4px;"><?= h($m['label'] ?: '—') ?></td>
                          <td style="padding:2px 4px;font-weight:600;">
                            <?= $m['display_ft'] ? number_format((float)$m['display_ft'], 1) . ' ft' : '—' ?>
                          </td>
                        </tr>
                      <?php endforeach; ?>
                    </table>
                  </div>
                </div>
                <?php endif; ?>

              </div><!-- /.sidebar -->
            </div><!-- /.main -->

            <!-- Footer / Disclaimer -->
            <div style="margin-top:10px;border-top:1px solid #ccc;padding-top:6px;font-size:7.5pt;color:#666;">
              <strong>Disclaimer:</strong>
              Site plan is for permit application and planning use only. Final survey verification may be required by the jurisdiction.
              Dimensions are approximate based on satellite imagery calibration. Not for construction staking.
              &copy; BaceBuilt LLC <?= date('Y') ?>.
              &nbsp;&nbsp;
              <strong>Notes:</strong> <?= h($project['notes'] ?: '—') ?>
            </div>

          </div><!-- /.export-preview -->
        </div>
      </div>
    </div>
  </div>
</div>

<script>
  // Minimal SITE_PLAN_DATA stub for export-preview.js print function
  const SITE_PLAN_DATA = {
    projectId: <?= $id ?>,
    project:   <?= json_encode($project, JSON_HEX_TAG) ?>,
    setbacks:  <?= json_encode($setbacks, JSON_HEX_TAG) ?>,
    objects:   <?= json_encode(array_values($objects), JSON_HEX_TAG) ?>,
    measurements: <?= json_encode(array_values($measurements), JSON_HEX_TAG) ?>,
    baseUrl:   '<?= BASE_URL ?>',
  };
</script>
<script src="<?= BASE_URL ?>/assets/js/export-preview.js"></script>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
