/**
 * export-preview.js
 * Captures the live Leaflet map via html2canvas and assembles a
 * print-ready permit sheet in a new window.
 */

(function () {
  'use strict';

  const D = SITE_PLAN_DATA;

  // ── Map capture ───────────────────────────────────────────────────────────

  function waitForTiles() {
    return new Promise(resolve => {
      const map = window.SitePlanMap;
      if (!map) { resolve(); return; }
      // If the map is already idle, resolve immediately
      let pending = 0;
      map.eachLayer(layer => {
        if (layer._tiles) {
          Object.values(layer._tiles).forEach(t => {
            if (!t.loaded) pending++;
          });
        }
      });
      if (pending === 0) { resolve(); return; }
      map.once('load', resolve);
      setTimeout(resolve, 4000); // hard timeout
    });
  }

  async function captureMapImage() {
    const mapEl = document.getElementById('map');
    if (!mapEl || !window.html2canvas) return null;

    // Hide drag handles — they're editor-only UI, not part of the permit plan
    const handles = mapEl.querySelectorAll('.sp-drag-handle');
    handles.forEach(el => { el.style.visibility = 'hidden'; });

    try {
      await waitForTiles();
      const canvas = await html2canvas(mapEl, {
        useCORS:         true,
        allowTaint:      false,
        scale:           1,
        logging:         false,
        imageTimeout:    15000,
        backgroundColor: '#dce8f0',
        removeContainer: true,
        onclone: (clonedDoc) => {
          // Leaflet positions the map pane AND every tile element via
          // CSS translate3d. html2canvas misapplies nested transforms,
          // shifting the SVG vector layer relative to tiles. Fix: convert
          // every translate3d inside the map to explicit left/top so
          // html2canvas sees only simple box-model positioning.
          clonedDoc.querySelectorAll('#map [style*="translate"]').forEach(el => {
            const t = el.style.transform;
            if (!t) return;
            const m = t.match(/translate3?d?\((-?[\d.]+)px,\s*(-?[\d.]+)px/);
            if (!m) return;
            const tx = parseFloat(m[1]);
            const ty = parseFloat(m[2]);
            // Strip the translate part; preserve scale() or other transforms
            el.style.transform = t.replace(/translate3?d?\([^)]+\)\s*/g, '').trim() || 'none';
            el.style.left = (parseFloat(el.style.left || '0') + tx) + 'px';
            el.style.top  = (parseFloat(el.style.top  || '0') + ty) + 'px';
          });
          // Hide drag handles
          clonedDoc.querySelectorAll('.sp-drag-handle').forEach(el => {
            el.style.display = 'none';
          });
        },
      });
      return canvas.toDataURL('image/jpeg', 0.90);
    } catch (err) {
      console.warn('Map capture failed:', err);
      return null;
    } finally {
      handles.forEach(el => { el.style.visibility = ''; });
    }
  }

  // ── Main export entry point ───────────────────────────────────────────────

  async function printPermitSheet() {
    const btn = document.getElementById('printSheetBtn');
    const origHtml = btn ? btn.innerHTML : null;
    if (btn) {
      btn.disabled = true;
      btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Capturing…';
    }

    const project      = D.project;
    const now          = new Date().toLocaleDateString('en-US', { year:'numeric', month:'long', day:'numeric' });
    const objects      = D.objects      || [];
    const measurements = D.measurements || [];
    const setbacks     = D.setbacks     || {};
    const existing     = objects.filter(o => o.status === 'existing');
    const proposed     = objects.filter(o => o.status === 'proposed');

    const mapImg = await captureMapImage();

    if (btn) { btn.disabled = false; btn.innerHTML = origHtml; }

    const html = buildPermitHTML(project, now, existing, proposed, measurements, setbacks, mapImg);
    const win  = window.open('', '_blank', 'width=1400,height=900');
    if (!win) { alert('Pop-up blocked — please allow pop-ups for this site.'); return; }
    win.document.write(html);
    win.document.close();
    win.focus();
    // Give the new window time to render, then trigger print
    setTimeout(() => win.print(), 1200);
  }

  // ── HTML assembly ─────────────────────────────────────────────────────────

  function buildPermitHTML(project, date, existing, proposed, measurements, setbacks, mapImg) {
    const allObjects   = [...existing, ...proposed];
    const structRows   = allObjects.map(o => `
      <tr>
        <td>${esc(o.label || o.structure_type)}</td>
        <td>${esc(ucfirst(o.status))}</td>
        <td>${o.width_ft && o.length_ft ? o.width_ft + '×' + o.length_ft : '—'}</td>
        <td>${o.sqft ? Math.round(o.sqft).toLocaleString() : '—'}</td>
      </tr>`).join('');

    const measRows = measurements.map(m => `
      <tr>
        <td>${esc(m.label || '—')}</td>
        <td><strong>${m.display_ft != null ? parseFloat(m.display_ft).toFixed(1) + ' ft' : '—'}</strong></td>
      </tr>`).join('');

    const mapSection = mapImg
      ? `<img src="${mapImg}" style="width:100%;height:100%;object-fit:cover;display:block;" alt="Site Plan Map">`
      : `<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;
                     height:100%;color:#999;padding:20px;text-align:center;">
           <div style="font-size:40pt;opacity:.15;">&#9648;</div>
           <strong>Satellite / Site Plan Image</strong><br>
           <span style="font-size:8pt;">Use ESRI or Mapbox basemap and reload for map capture.</span>
         </div>`;

    return `<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>Site Plan — ${esc(project.project_name)}</title>
<style>
  @page { size: 17in 11in landscape; margin: 0.4in; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 9pt; color: #111; margin: 0; }
  .header { display: flex; justify-content: space-between; align-items: flex-start;
            border-bottom: 3px solid #1A5276; padding-bottom: 8px; margin-bottom: 10px; }
  .header h1 { font-size: 13pt; margin: 0 0 3px 0; color: #1A5276; }
  .meta { font-size: 8pt; color: #444; margin-top: 2px; }
  .main { display: flex; gap: 12px; }
  .map-area { flex: 1; border: 2px solid #1A5276; min-height: 460px; background: #dce8f0;
               border-radius: 3px; position: relative; overflow: hidden; }
  .north { position: absolute; top: 8px; right: 8px; background: rgba(255,255,255,.9);
            border: 1px solid #999; border-radius: 3px; padding: 3px 6px;
            font-size: 8pt; font-weight: bold; text-align: center; line-height: 1.3; z-index:10; }
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
  .footer { margin-top: 8px; border-top: 1px solid #ccc; padding-top: 5px; font-size: 7.5pt; color: #666; }
  @media print { * { -webkit-print-color-adjust: exact; print-color-adjust: exact; } }
</style>
</head>
<body>

<div class="header">
  <div>
    <h1>BaceBuilt LLC &mdash; Permit Site Plan</h1>
    <div class="meta">
      <strong>Project:</strong> ${esc(project.project_name)} &nbsp;|&nbsp;
      <strong>Client:</strong> ${esc(project.client_name)} &nbsp;|&nbsp;
      <strong>Date:</strong> ${esc(date)}
    </div>
    <div class="meta">
      <strong>Address:</strong> ${esc(project.site_address)}, ${esc(project.city)}, ${esc(project.state)} ${esc(project.zip)}
      &nbsp;|&nbsp; <strong>Parcel:</strong> ${esc(project.parcel_number || '—')}
      &nbsp;|&nbsp; <strong>Jurisdiction:</strong> ${esc(project.jurisdiction)}
      &nbsp;|&nbsp; <strong>Type:</strong> ${esc(project.project_type || '')}
    </div>
  </div>
  <div style="text-align:right;min-width:140px;">
    <div style="font-weight:bold;color:#1A5276;font-size:11pt;">BaceBuilt LLC</div>
    <div class="meta">Construction &amp; Site Development</div>
    <div class="meta">Scale: Per calibration &nbsp;|&nbsp; ↑ North</div>
    <div class="meta">Paper: LEDGER Landscape</div>
  </div>
</div>

<div class="main">
  <div class="map-area">
    ${mapSection}
    <div class="north">↑<br>N</div>
  </div>

  <div class="sidebar">
    <div class="panel">
      <div class="panel-hdr">Legend</div>
      <div class="panel-body">
        ${legendHTML()}
      </div>
    </div>

    <div class="panel">
      <div class="panel-hdr">Setback Requirements</div>
      <div class="panel-body">
        <table>
          <tr><td>Front Setback</td><td><strong>${setbacks.front_ft || 20} ft</strong></td></tr>
          <tr><td>Rear Setback</td><td><strong>${setbacks.rear_ft || 5} ft</strong></td></tr>
          <tr><td>Side Setback</td><td><strong>${setbacks.side_ft || 5} ft</strong></td></tr>
          <tr><td>Accessory</td><td><strong>${setbacks.accessory_ft || 5} ft</strong></td></tr>
          <tr><td>Well Separation</td><td><strong>${setbacks.well_sep_ft || 100} ft</strong></td></tr>
          <tr><td>Septic Separation</td><td><strong>${setbacks.septic_sep_ft || 100} ft</strong></td></tr>
        </table>
      </div>
    </div>

    ${structRows ? `<div class="panel">
      <div class="panel-hdr">Structures</div>
      <div class="panel-body">
        <table>
          <tr><th>Label</th><th>Status</th><th>W×L</th><th>SF</th></tr>
          ${structRows}
        </table>
      </div>
    </div>` : ''}

    ${measRows ? `<div class="panel">
      <div class="panel-hdr">Measurements</div>
      <div class="panel-body">
        <table>
          <tr><th>Label</th><th>Distance</th></tr>
          ${measRows}
        </table>
      </div>
    </div>` : ''}
  </div>
</div>

<div class="footer">
  <strong>Disclaimer:</strong>
  Site plan is for permit application and planning use only. Final survey verification may be required by the jurisdiction.
  Dimensions are approximate based on satellite imagery calibration. Not for construction staking.
  &copy; BaceBuilt LLC ${new Date().getFullYear()}.
  &nbsp;&mdash;&nbsp;
  <strong>Notes:</strong> ${esc(project.notes || '—')}
</div>

</body></html>`;
  }

  function legendHTML() {
    const items = [
      ['#AED6F1','#1A5276','solid',  'Existing House'],
      ['#A9DFBF','#1E8449','dashed', 'Proposed Structure'],
      ['#D5D8DC','#566573','solid',  'Garage / Accessory'],
      ['transparent','#8E44AD','dashed','Parcel Boundary'],
      ['transparent','#E74C3C','dashed','Setback Lines'],
      ['transparent','#E74C3C','solid', 'Measurements'],
      ['#CCD1D1','#717D7E','solid',  'Driveway'],
      ['#85C1E9','#1A5276','solid',  'Well / Water'],
      ['#A9CCE3','#1A5276','solid',  'Septic'],
    ];
    return items.map(([fill, brd, dash, lbl]) =>
      `<div class="legend-row">
         <div class="swatch" style="background:${fill};border-color:${brd};border-style:${dash};"></div>
         ${esc(lbl)}
       </div>`
    ).join('');
  }

  function esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function ucfirst(s) {
    s = String(s || '');
    return s.charAt(0).toUpperCase() + s.slice(1);
  }

  // ── Wire up buttons ───────────────────────────────────────────────────────

  document.getElementById('printSheetBtn')?.addEventListener('click', printPermitSheet);

  // Auto-export mode: triggered when editor opens with ?export=1
  if (new URLSearchParams(location.search).get('export') === '1') {
    const waitForMap = setInterval(() => {
      if (window.SitePlanMap && window.SitePlanLayers) {
        clearInterval(waitForMap);
        // Extra delay to let tiles render
        setTimeout(printPermitSheet, 2500);
      }
    }, 200);
  }

  // ── Expose ─────────────────────────────────────────────────────────────────
  window.SitePlanExport = { printPermitSheet, buildPermitHTML };

})();
