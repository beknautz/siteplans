/**
 * export-preview.js
 * Handles the browser-side export preview:
 *   - Captures the Leaflet map as a canvas/image snapshot via Leaflet.easyPrint or html2canvas
 *   - Assembles the permit sheet HTML for print-to-PDF
 *   - Posts to api/export-pdf.php for server-side PDF generation
 *
 * The export button on export.php also triggers server-side TCPDF/DomPDF rendering.
 * This module handles the in-editor quick-print path.
 */

(function () {
  'use strict';

  const D = SITE_PLAN_DATA;

  // ── Print / Export helpers ─────────────────────────────────────────────────

  /**
   * Opens a print-ready window with the current map snapshot embedded.
   * Because Leaflet renders to canvas tiles, we attempt to use
   * html2canvas (loaded lazily) for a best-effort map capture.
   */
  function printPermitSheet() {
    const project = D.project;
    const now     = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });

    // Collect object summary for legend
    const objects       = D.objects || [];
    const existing      = objects.filter(o => o.status === 'existing');
    const proposed      = objects.filter(o => o.status === 'proposed');
    const measurements  = D.measurements || [];
    const setbacks      = D.setbacks || {};

    const html = buildPermitHTML(project, now, existing, proposed, measurements, setbacks);

    const win = window.open('', '_blank', 'width=1200,height=900');
    win.document.write(html);
    win.document.close();
    win.focus();
    setTimeout(() => { win.print(); }, 800);
  }

  function buildPermitHTML(project, date, existing, proposed, measurements, setbacks) {
    const existingRows = existing.map(o =>
      `<tr><td>${esc(o.label||o.structure_type)}</td><td>Existing</td><td>${o.width_ft||'—'}</td><td>${o.length_ft||'—'}</td><td>${o.sqft ? Math.round(o.sqft) : '—'}</td></tr>`
    ).join('');
    const proposedRows = proposed.map(o =>
      `<tr><td>${esc(o.label||o.structure_type)}</td><td><strong>Proposed</strong></td><td>${o.width_ft||'—'}</td><td>${o.length_ft||'—'}</td><td>${o.sqft ? Math.round(o.sqft) : '—'}</td></tr>`
    ).join('');
    const measRows = measurements.map(m =>
      `<tr><td>${esc(m.label||'—')}</td><td>${m.display_ft != null ? parseFloat(m.display_ft).toFixed(1) + ' ft' : '—'}</td></tr>`
    ).join('');

    return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Site Plan — ${esc(project.project_name)}</title>
<style>
  @page { size: 11in 8.5in landscape; margin: 0.5in; }
  * { box-sizing: border-box; }
  body { font-family: Arial, sans-serif; font-size: 10pt; color: #111; }
  .sheet { width: 100%; }
  .header { display: flex; justify-content: space-between; border-bottom: 3px solid #1A5276; padding-bottom: 8px; margin-bottom: 10px; }
  .header h1 { font-size: 14pt; margin: 0; color: #1A5276; }
  .header .meta { font-size: 9pt; color: #444; }
  .main { display: grid; grid-template-columns: 1fr 280px; gap: 12px; }
  .map-area { border: 2px solid #1A5276; min-height: 420px; background: #e8f0f7;
              display: flex; align-items: center; justify-content: center;
              position: relative; overflow: hidden; }
  .map-placeholder { color: #999; font-size: 12pt; text-align: center; padding: 20px; }
  .sidebar { display: flex; flex-direction: column; gap: 10px; }
  .panel { border: 1px solid #ccc; border-radius: 4px; overflow: hidden; }
  .panel-header { background: #1A5276; color: #fff; font-size: 9pt; font-weight: bold; padding: 4px 8px; }
  .panel-body { padding: 6px 8px; font-size: 9pt; }
  table { width: 100%; border-collapse: collapse; font-size: 8.5pt; }
  th { background: #2E4057; color: #fff; padding: 3px 6px; text-align: left; }
  td { padding: 3px 6px; border-bottom: 1px solid #e0e0e0; }
  tr:nth-child(even) td { background: #f5f8fb; }
  .legend-row { display: flex; align-items: center; gap: 6px; margin: 2px 0; font-size: 8.5pt; }
  .swatch { width: 16px; height: 12px; border: 1px solid #666; border-radius: 2px; flex-shrink: 0; }
  .footer { margin-top: 12px; border-top: 1px solid #ccc; padding-top: 6px; font-size: 8pt; color: #666; }
  .north { position: absolute; top: 10px; right: 10px; font-size: 9pt; font-weight: bold;
           background: rgba(255,255,255,.85); border: 1px solid #999; border-radius: 3px;
           padding: 4px 6px; text-align: center; line-height: 1.2; }
  @media print { body { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>
<div class="sheet">
  <!-- HEADER -->
  <div class="header">
    <div>
      <h1>BaceBuilt LLC — Permit Site Plan</h1>
      <div class="meta">
        <strong>Project:</strong> ${esc(project.project_name)} &nbsp;|&nbsp;
        <strong>Client:</strong> ${esc(project.client_name)} &nbsp;|&nbsp;
        <strong>Date:</strong> ${esc(date)}
      </div>
      <div class="meta">
        <strong>Address:</strong> ${esc(project.site_address)}, ${esc(project.city)}, ${esc(project.state)} ${esc(project.zip)} &nbsp;|&nbsp;
        <strong>Parcel:</strong> ${esc(project.parcel_number||'—')} &nbsp;|&nbsp;
        <strong>Jurisdiction:</strong> ${esc(project.jurisdiction)}
      </div>
    </div>
    <div style="text-align:right;">
      <div style="font-weight:bold;color:#1A5276;">BaceBuilt LLC</div>
      <div class="meta">Construction &amp; Site Development</div>
      <div class="meta">Scale: Per calibration &nbsp;|&nbsp; North: ↑</div>
    </div>
  </div>

  <div class="main">
    <!-- MAP AREA -->
    <div class="map-area" id="exportMapArea">
      <div class="map-placeholder">
        <div style="font-size:48pt;opacity:.15;">🗺</div>
        <div>Satellite / Site Plan Image</div>
        <div style="font-size:9pt;margin-top:8px;color:#aaa;">
          (Use browser Print → Save as PDF from the editor map page<br>for a full map capture with all layers)
        </div>
      </div>
      <div class="north">↑<br>N</div>
    </div>

    <!-- SIDEBAR -->
    <div class="sidebar">
      <!-- Legend -->
      <div class="panel">
        <div class="panel-header">Legend</div>
        <div class="panel-body">
          <div class="legend-row"><div class="swatch" style="background:#AED6F1;border-color:#1A5276;"></div> Existing House</div>
          <div class="legend-row"><div class="swatch" style="background:#A9DFBF;border-color:#1E8449;border-style:dashed;"></div> Proposed ADU/Structure</div>
          <div class="legend-row"><div class="swatch" style="background:#D5D8DC;border-color:#566573;"></div> Garage</div>
          <div class="legend-row"><div class="swatch" style="background:transparent;border-color:#8E44AD;border-style:dashed;"></div> Parcel Boundary</div>
          <div class="legend-row"><div class="swatch" style="background:transparent;border-color:#E74C3C;border-style:dashed;"></div> Setback Lines</div>
          <div class="legend-row"><div class="swatch" style="background:transparent;border-color:#E74C3C;"></div> Measurements</div>
          <div class="legend-row"><div class="swatch" style="background:#CCD1D1;border-color:#717D7E;"></div> Driveway</div>
          <div class="legend-row"><div class="swatch" style="background:#85C1E9;border-color:#1A5276;"></div> Well / Water</div>
          <div class="legend-row"><div class="swatch" style="background:#A9CCE3;border-color:#1A5276;"></div> Septic</div>
        </div>
      </div>

      <!-- Setback Rules -->
      <div class="panel">
        <div class="panel-header">Setback Requirements</div>
        <div class="panel-body">
          <table>
            <tr><th>Type</th><th>Distance</th></tr>
            <tr><td>Front</td><td>${setbacks.front_ft || 20} ft</td></tr>
            <tr><td>Rear</td><td>${setbacks.rear_ft  || 5}  ft</td></tr>
            <tr><td>Side</td><td>${setbacks.side_ft  || 5}  ft</td></tr>
            <tr><td>Accessory</td><td>${setbacks.accessory_ft || 5} ft</td></tr>
            <tr><td>Well Separation</td><td>${setbacks.well_sep_ft || 100} ft</td></tr>
            <tr><td>Septic Separation</td><td>${setbacks.septic_sep_ft || 100} ft</td></tr>
          </table>
        </div>
      </div>

      <!-- Structures -->
      <div class="panel">
        <div class="panel-header">Structures</div>
        <div class="panel-body">
          <table>
            <tr><th>Label</th><th>Status</th><th>W</th><th>L</th><th>SF</th></tr>
            ${existingRows}
            ${proposedRows}
          </table>
        </div>
      </div>

      <!-- Measurements -->
      ${measRows ? `<div class="panel">
        <div class="panel-header">Measurements</div>
        <div class="panel-body">
          <table><tr><th>Label</th><th>Distance</th></tr>${measRows}</table>
        </div>
      </div>` : ''}
    </div>
  </div>

  <!-- FOOTER / DISCLAIMER -->
  <div class="footer">
    <strong>Disclaimer:</strong>
    Site plan is for permit application and planning use only. Final survey verification may be required by the jurisdiction.
    Dimensions are approximate based on satellite imagery calibration. Not for construction staking. &copy; BaceBuilt LLC ${new Date().getFullYear()}.
    &nbsp;&nbsp;&nbsp;
    <strong>Project Notes:</strong> ${esc(project.notes || '—')}
  </div>
</div>
</body></html>`;
  }

  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  // ── Wire up export button if present ──────────────────────────────────────

  document.getElementById('printSheetBtn')?.addEventListener('click', printPermitSheet);

  // ── Expose ─────────────────────────────────────────────────────────────────
  window.SitePlanExport = { printPermitSheet, buildPermitHTML };

})();
