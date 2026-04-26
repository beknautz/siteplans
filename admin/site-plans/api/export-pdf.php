<?php
/**
 * api/export-pdf.php
 * Server-side PDF generation using TCPDF (if installed) or a clean HTML fallback.
 *
 * TCPDF install:   composer require tecnickcom/tcpdf
 * DOMPDF install:  composer require dompdf/dompdf
 *
 * If neither is installed the endpoint records the export and redirects back
 * to export.php with a "use browser print" message.
 */
require_once dirname(__DIR__, 3) . '/config/database.php';
require_once dirname(__DIR__, 3) . '/includes/helpers.php';
safe_session_start();
require_login();
csrf_check();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/admin/site-plans/index.php');
}

$project_id  = (int)($_POST['project_id'] ?? 0);
$paper_size  = in_array($_POST['paper_size']  ?? '', ['letter','ledger']) ? $_POST['paper_size']  : 'ledger';
$orientation = in_array($_POST['orientation'] ?? '', ['landscape','portrait']) ? $_POST['orientation'] : 'landscape';
$export_type = in_array($_POST['export_type'] ?? '', ['pdf','print']) ? $_POST['export_type'] : 'pdf';
$notes       = trim($_POST['notes'] ?? '');

if (!$project_id) {
    flash_set('error', 'Invalid project.');
    redirect(BASE_URL . '/admin/site-plans/index.php');
}

$project      = db_row('SELECT * FROM projects WHERE id=:id', [':id' => $project_id]);
if (!$project) {
    flash_set('error', 'Project not found.');
    redirect(BASE_URL . '/admin/site-plans/export.php?id=' . $project_id);
}

$setbacks     = db_row('SELECT * FROM setback_rules WHERE project_id=:pid', [':pid' => $project_id])
                 ?? ['front_ft'=>20,'rear_ft'=>5,'side_ft'=>5,'accessory_ft'=>5,'well_sep_ft'=>100,'septic_sep_ft'=>100];
$objects      = db_rows('SELECT * FROM site_plan_objects WHERE project_id=:pid AND visible=1 ORDER BY sort_order,id', [':pid' => $project_id]);
$measurements = db_rows('SELECT * FROM site_plan_measurements WHERE project_id=:pid AND visible=1', [':pid' => $project_id]);

// Record export in DB
db_insert(
    "INSERT INTO permit_exports (project_id, export_type, paper_size, orientation, notes)
     VALUES (:pid, :type, :paper, :orient, :notes)",
    [
        ':pid'    => $project_id,
        ':type'   => $export_type,
        ':paper'  => $paper_size,
        ':orient' => $orientation,
        ':notes'  => $notes ?: null,
    ]
);

// ── Build HTML permit sheet ───────────────────────────────────────────────────

$now = date('F j, Y');

ob_start();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Site Plan — <?= h($project['project_name']) ?></title>
<style>
  @page { size: <?= $paper_size === 'ledger' ? '17in 11in' : '11in 8.5in' ?> <?= $orientation ?>; margin: 0.5in; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #111; margin: 0; }
  .header { display: flex; justify-content: space-between; border-bottom: 3px solid #1A5276; padding-bottom: 8px; margin-bottom: 10px; }
  .header h1 { font-size: 13pt; margin: 0; color: #1A5276; }
  .meta { font-size: 8pt; color: #444; margin-top: 2px; }
  .main { display: flex; gap: 12px; }
  .map-area { flex: 1; border: 2px solid #1A5276; min-height: 420px; background: #dce8f0;
               display: flex; align-items: center; justify-content: center; border-radius: 3px; position: relative; }
  .map-placeholder { text-align: center; color: #999; padding: 30px; }
  .sidebar { width: 220px; flex-shrink: 0; display: flex; flex-direction: column; gap: 8px; }
  .panel { border: 1px solid #ccc; border-radius: 3px; overflow: hidden; }
  .panel-hdr { background: #1A5276; color: #fff; padding: 3px 8px; font-weight: bold; font-size: 8.5pt; }
  .panel-body { padding: 5px 8px; }
  table { width: 100%; border-collapse: collapse; font-size: 8pt; }
  th { background: #2E4057; color: #fff; padding: 2px 5px; text-align: left; }
  td { padding: 2px 5px; border-bottom: 1px solid #eee; }
  tr:nth-child(even) td { background: #f5f8fb; }
  .legend-row { display: flex; align-items: center; gap: 5px; margin: 2px 0; font-size: 8pt; }
  .swatch { width: 14px; height: 10px; border: 1px solid; flex-shrink: 0; }
  .north { position: absolute; top: 8px; right: 8px; background: rgba(255,255,255,.85);
            border: 1px solid #999; border-radius: 3px; padding: 3px 5px;
            font-size: 8pt; font-weight: bold; text-align: center; line-height: 1.3; }
  .footer { margin-top: 8px; border-top: 1px solid #ccc; padding-top: 5px; font-size: 7.5pt; color: #666; }
  @media print { * { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>
<!-- Header -->
<div class="header">
  <div>
    <h1>BaceBuilt LLC &mdash; Permit Site Plan</h1>
    <div class="meta">
      <strong>Project:</strong> <?= h($project['project_name']) ?> &nbsp;|&nbsp;
      <strong>Client:</strong> <?= h($project['client_name']) ?> &nbsp;|&nbsp;
      <strong>Date:</strong> <?= h($now) ?>
    </div>
    <div class="meta">
      <strong>Address:</strong> <?= h($project['site_address']) ?>, <?= h($project['city']) ?>, <?= h($project['state']) ?> <?= h($project['zip']) ?>
      &nbsp;|&nbsp;
      <strong>Parcel:</strong> <?= h($project['parcel_number'] ?: '—') ?>
      &nbsp;|&nbsp;
      <strong>Jurisdiction:</strong> <?= h($project['jurisdiction']) ?>
      &nbsp;|&nbsp;
      <strong>Type:</strong> <?= project_type_label($project['project_type']) ?>
    </div>
  </div>
  <div style="text-align:right;">
    <div style="font-weight:bold;color:#1A5276;font-size:11pt;">BaceBuilt LLC</div>
    <div class="meta">Construction &amp; Site Development</div>
    <div class="meta">Scale: Per calibration &nbsp;|&nbsp; ↑ North</div>
    <div class="meta">Paper: <?= strtoupper($paper_size) ?> <?= ucfirst($orientation) ?></div>
  </div>
</div>

<!-- Main body -->
<div class="main">
  <!-- Map placeholder -->
  <div class="map-area">
    <div class="map-placeholder">
      <div style="font-size:48pt;opacity:.1;line-height:1;">◈</div>
      <strong>Site Plan / Satellite Image</strong><br>
      <span style="font-size:7.5pt;">Use browser Print → Save as PDF from the editor for full map capture</span>
    </div>
    <div class="north">↑<br>N</div>
  </div>

  <!-- Sidebar panels -->
  <div class="sidebar">
    <!-- Legend -->
    <div class="panel">
      <div class="panel-hdr">Legend</div>
      <div class="panel-body">
        <?php
        $legend = [
          ['#AED6F1','solid','#1A5276',  'Existing House'],
          ['#A9DFBF','dashed','#1E8449', 'Proposed Structure'],
          ['#D5D8DC','solid','#566573',  'Garage / Accessory'],
          ['transparent','dashed','#8E44AD','Parcel Boundary'],
          ['transparent','dashed','#E74C3C','Setback Lines'],
          ['transparent','solid','#E74C3C', 'Measurements'],
          ['#CCD1D1','solid','#717D7E',  'Driveway'],
          ['#85C1E9','solid','#1A5276',  'Well / Water'],
          ['#A9CCE3','solid','#1A5276',  'Septic'],
        ];
        foreach ($legend as [$fill, $dash, $brd, $lbl]):
        ?>
          <div class="legend-row">
            <div class="swatch" style="background:<?= $fill ?>;border-style:<?= $dash ?>;border-color:<?= $brd ?>;"></div>
            <?= h($lbl) ?>
          </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Setbacks -->
    <div class="panel">
      <div class="panel-hdr">Setback Requirements</div>
      <div class="panel-body">
        <table>
          <?php foreach ([
            'Front Setback'     => $setbacks['front_ft'],
            'Rear Setback'      => $setbacks['rear_ft'],
            'Side Setback'      => $setbacks['side_ft'],
            'Accessory'         => $setbacks['accessory_ft'],
            'Well Separation'   => $setbacks['well_sep_ft'],
            'Septic Separation' => $setbacks['septic_sep_ft'],
          ] as $type => $dist): ?>
            <tr>
              <td><?= h($type) ?></td>
              <td style="font-weight:600;"><?= number_format((float)$dist, 1) ?> ft</td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>

    <!-- Structures table -->
    <?php if ($objects): ?>
    <div class="panel">
      <div class="panel-hdr">Structures</div>
      <div class="panel-body">
        <table>
          <tr><th>Label</th><th>Status</th><th>W×L</th><th>SF</th></tr>
          <?php foreach ($objects as $o): ?>
            <tr>
              <td><?= h($o['label'] ?: ucfirst($o['structure_type'] ?: $o['object_type'])) ?></td>
              <td><?= h(ucfirst($o['status'])) ?></td>
              <td><?= $o['width_ft'] && $o['length_ft'] ? number_format((float)$o['width_ft'],1).'×'.number_format((float)$o['length_ft'],1) : '—' ?></td>
              <td><?= $o['sqft'] ? number_format((float)$o['sqft']) : '—' ?></td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
    <?php endif; ?>

    <!-- Measurements -->
    <?php if ($measurements): ?>
    <div class="panel">
      <div class="panel-hdr">Measurements</div>
      <div class="panel-body">
        <table>
          <tr><th>Label</th><th>Distance</th></tr>
          <?php foreach ($measurements as $m): ?>
            <tr>
              <td><?= h($m['label'] ?: '—') ?></td>
              <td style="font-weight:600;">
                <?= $m['display_ft'] ? number_format((float)$m['display_ft'], 1).' ft' : '—' ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    </div>
    <?php endif; ?>

  </div><!-- /.sidebar -->
</div><!-- /.main -->

<!-- Notes -->
<?php if ($notes): ?>
<div style="margin-top:6px;padding:5px 8px;background:#fffde7;border:1px solid #f0c000;border-radius:3px;font-size:8pt;">
  <strong>Export Notes:</strong> <?= h($notes) ?>
</div>
<?php endif; ?>

<!-- Footer / Disclaimer -->
<div class="footer">
  <strong>Disclaimer:</strong>
  Site plan is for permit application and planning use only.
  Final survey verification may be required by the jurisdiction.
  Dimensions are approximate based on satellite imagery calibration. Not for construction staking.
  &copy; BaceBuilt LLC <?= date('Y') ?>.
  &nbsp;&mdash;&nbsp;
  <?= h($project['notes'] ? 'Notes: '.$project['notes'] : '') ?>
</div>

</body></html>
<?php
$html = ob_get_clean();

// ── Try TCPDF if available ────────────────────────────────────────────────────
$tcpdf_path   = BASE_PATH . '/vendor/tcpdf/tcpdf.php';
$dompdf_path  = BASE_PATH . '/vendor/autoload.php';

if (file_exists($tcpdf_path)) {
    require_once $tcpdf_path;

    $pageFormat = $paper_size === 'ledger' ? 'TABLOID' : 'LETTER';
    $orient     = strtoupper($orientation[0]);   // 'L' or 'P'

    $pdf = new TCPDF($orient, 'in', $pageFormat, true, 'UTF-8');
    $pdf->SetCreator('BaceBuilt Site Plan Tool');
    $pdf->SetAuthor('BaceBuilt LLC');
    $pdf->SetTitle($project['project_name'] . ' — Permit Site Plan');
    $pdf->SetMargins(0.5, 0.5, 0.5);
    $pdf->SetAutoPageBreak(true, 0.5);
    $pdf->AddPage();
    $pdf->writeHTML($html, true, false, true, false, '');
    $pdf->Output('site-plan-' . $project_id . '-' . date('Ymd') . '.pdf', 'D');
    exit;

} elseif (file_exists($dompdf_path)) {
    require_once $dompdf_path;

    $options = new \Dompdf\Options();
    $options->set('isRemoteEnabled', false);
    $options->set('defaultFont', 'Helvetica');
    $dompdf = new \Dompdf\Dompdf($options);
    $dompdf->loadHtml($html);

    $pageSize = $paper_size === 'ledger' ? [0, 0, 842.4, 1224] : [0, 0, 612, 792];
    $orient   = $orientation === 'landscape' ? \Dompdf\Dompdf::ORIENTATION_LANDSCAPE : \Dompdf\Dompdf::ORIENTATION_PORTRAIT;
    $dompdf->setPaper($pageSize, $orient);
    $dompdf->render();

    $filename = 'site-plan-' . $project_id . '-' . date('Ymd') . '.pdf';
    $dompdf->stream($filename, ['Attachment' => true]);
    exit;

} else {
    // No PDF library — stream HTML for browser print
    flash_set('info', 'PDF library not installed. Use File → Print → Save as PDF from the browser window that opened.');
    header('Content-Type: text/html; charset=UTF-8');
    echo $html;
    exit;
}
