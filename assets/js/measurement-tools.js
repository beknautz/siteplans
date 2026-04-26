/**
 * measurement-tools.js
 * Distance measurement tool: click two points, see labeled distance line.
 * Distances computed from Leaflet's built-in distanceTo() (meters → feet).
 * If a user-calibrated scale factor exists it is used for pixel-based fallback.
 * Saves to DB via api/save-measurement.php.
 */

(function () {
  'use strict';

  const D = SITE_PLAN_DATA;

  let state = 'idle';     // 'idle' | 'first' | 'second'
  let pointA = null;
  let markerA = null;
  let previewLine = null;

  // All measurement layers rendered on the map (keyed by DB id)
  const measureLayers = {};

  const METERS_PER_FT = 0.3048;

  // ── Render all saved measurements on load ─────────────────────────────────

  function renderAllMeasurements() {
    (D.measurements || []).forEach(m => renderMeasurement(m));
  }

  function renderMeasurement(m) {
    if (m.geometry_type === 'latlng') {
      if (!m.from_lat || !m.to_lat) return;
      _drawMeasurementLine(
        { lat: parseFloat(m.from_lat), lng: parseFloat(m.from_lng) },
        { lat: parseFloat(m.to_lat),   lng: parseFloat(m.to_lng) },
        m
      );
    }
  }

  function _drawMeasurementLine(from, to, data) {
    const group = window.SitePlanLayers?.measurements;
    if (!group) return;

    const color = data.color || '#E74C3C';
    const displayFt = data.override_ft != null
      ? parseFloat(data.override_ft)
      : (data.display_ft != null ? parseFloat(data.display_ft) : null);
    const ftLabel = displayFt != null ? `${displayFt.toFixed(1)} ft` : '';

    const line = L.polyline([[from.lat, from.lng],[to.lat, to.lng]], {
      color,
      weight:    2,
      dashArray: '5 3',
    });

    // Tick marks at endpoints
    const markerA = L.circleMarker([from.lat, from.lng], {
      radius: 4, color, fillColor: '#fff', fillOpacity: 1, weight: 2,
    });
    const markerB = L.circleMarker([to.lat, to.lng], {
      radius: 4, color, fillColor: '#fff', fillOpacity: 1, weight: 2,
    });

    // Midpoint label
    const midLat = (from.lat + to.lat) / 2;
    const midLng = (from.lng + to.lng) / 2;
    const labelStr = (data.label ? `${data.label}: ` : '') + ftLabel;
    const labelMarker = L.marker([midLat, midLng], {
      icon: L.divIcon({
        className: 'sp-meas-label',
        html: `<div style="color:${color};font-size:11px;font-weight:700;white-space:nowrap;
                     background:rgba(0,0,0,.55);padding:1px 4px;border-radius:3px;">
                 ${escHtml(labelStr)}
               </div>`,
        iconAnchor: [0, 0],
      }),
      interactive: true,
    });

    // Click on line or label → select measurement in right panel
    [line, labelMarker].forEach(l => l.on('click', function (e) {
      L.DomEvent.stopPropagation(e);
      selectMeasurement(data);
    }));

    line.addTo(group);
    markerA.addTo(group);
    markerB.addTo(group);
    labelMarker.addTo(group);

    measureLayers[data.id] = { line, markerA, markerB, labelMarker, data };
    return { line, markerA, markerB, labelMarker };
  }

  // ── Select measurement → populate right panel ─────────────────────────────

  function selectMeasurement(data) {
    document.getElementById('noObjectSelected').style.display  = 'none';
    document.getElementById('objectProperties').style.display  = 'none';
    document.getElementById('measurementPanel').style.display  = 'block';

    document.getElementById('measId').value        = data.id || '';
    document.getElementById('measLabel').value     = data.label || '';
    document.getElementById('measCalcFt').value    = data.calculated_ft != null ? parseFloat(data.calculated_ft).toFixed(1) : '';
    document.getElementById('measOverrideFt').value= data.override_ft  != null ? parseFloat(data.override_ft).toFixed(1) : '';
    document.getElementById('measColor').value     = data.color || '#E74C3C';
  }

  // ── Add point (called from drawing-tools.js onMapClick) ──────────────────

  function addPoint(latlng) {
    if (state === 'idle' || state === 'first') {
      if (state === 'idle') {
        // First point
        state  = 'first';
        pointA = latlng;
        markerA = L.circleMarker([latlng.lat, latlng.lng], {
          radius: 5, color: '#E74C3C', fillColor: '#fff', fillOpacity: 1, weight: 2,
        }).addTo(window.SitePlanLayers?.measurements || window.SitePlanMap);

        // Preview line follows mouse
        window.SitePlanMap?.on('mousemove', onMouseMove);
        showToast('Now click the end point to finish measurement.');
      }
    } else if (state === 'first') {
      // Should not happen — handled above
    }

    if (state === 'first' && pointA && latlng !== pointA) {
      // We already set pointA; this second click is end point
      finishMeasurement(latlng);
    }
  }

  // addPoint is called twice: once when state=idle→first, once when state=first
  // Fix: track internally
  let _waitingForEnd = false;

  function addPoint2(latlng) {
    if (!_waitingForEnd) {
      // First click
      pointA = latlng;
      _waitingForEnd = true;
      markerA = L.circleMarker([latlng.lat, latlng.lng], {
        radius: 5, color: '#E74C3C', fillColor: '#fff', fillOpacity: 1, weight: 2,
      }).addTo(window.SitePlanLayers?.measurements || window.SitePlanMap);
      window.SitePlanMap?.on('mousemove', onMouseMove);
      showToast('Now click the second point to complete measurement.');
    } else {
      // Second click → finish
      _waitingForEnd = false;
      window.SitePlanMap?.off('mousemove', onMouseMove);
      if (previewLine) { previewLine.remove(); previewLine = null; }
      if (markerA)     { /* keep as part of saved line */ }

      const from = pointA;
      const to   = latlng;

      // Distance in feet using Leaflet's distanceTo
      const meters = window.SitePlanMap.distance(
        L.latLng(from.lat, from.lng),
        L.latLng(to.lat, to.lng)
      );
      const calcFt = meters / METERS_PER_FT;

      const measData = {
        project_id:    D.projectId,
        label:         `${calcFt.toFixed(1)} ft`,
        from_lat:      from.lat,
        from_lng:      from.lng,
        to_lat:        to.lat,
        to_lng:        to.lng,
        geometry_type: 'latlng',
        calculated_ft: parseFloat(calcFt.toFixed(3)),
        display_ft:    parseFloat(calcFt.toFixed(3)),
        color:         '#E74C3C',
      };

      // Remove the temp markerA since _drawMeasurementLine will add its own
      if (markerA) { markerA.remove(); markerA = null; }

      saveMeasurement(measData, saved => {
        _drawMeasurementLine(from, to, saved);
        showToast(`Measurement saved: ${saved.display_ft?.toFixed(1)} ft`);
      });

      pointA = null;
    }
  }

  function onMouseMove(e) {
    if (!pointA) return;
    const pts = [[pointA.lat, pointA.lng],[e.latlng.lat, e.latlng.lng]];
    if (!previewLine) {
      previewLine = L.polyline(pts, { color: '#E74C3C', weight: 2, dashArray: '4 4' })
        .addTo(window.SitePlanLayers?.measurements || window.SitePlanMap);
    } else {
      previewLine.setLatLngs(pts);
    }
    // Show live distance in toast area
    const m  = window.SitePlanMap.distance(
      L.latLng(pointA.lat, pointA.lng),
      L.latLng(e.latlng.lat, e.latlng.lng)
    );
    window.setSaveStatus?.(`~${(m / METERS_PER_FT).toFixed(1)} ft`);
  }

  // ── Save measurement to DB ─────────────────────────────────────────────────

  function saveMeasurement(data, callback) {
    const payload = Object.assign({}, data, { csrf_token: getCsrfToken() });
    fetch(`${D.baseUrl}/admin/site-plans/api/save-measurement.php`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
      body:    JSON.stringify(payload),
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          data.id = res.id;
          data.display_ft = res.display_ft;
          callback?.(data);
        }
      })
      .catch(err => console.error('save-measurement', err));
  }

  // ── Save measurement from right-panel ─────────────────────────────────────

  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('saveMeasurementBtn')?.addEventListener('click', () => {
      const id          = parseInt(document.getElementById('measId').value) || 0;
      const label       = document.getElementById('measLabel').value;
      const override_ft = document.getElementById('measOverrideFt').value;
      const calc_ft     = parseFloat(document.getElementById('measCalcFt').value) || null;
      const color       = document.getElementById('measColor').value;

      const payload = {
        id,
        project_id:    D.projectId,
        label,
        override_ft:   override_ft !== '' ? parseFloat(override_ft) : null,
        calculated_ft: calc_ft,
        color,
        csrf_token:    getCsrfToken(),
      };

      fetch(`${D.baseUrl}/admin/site-plans/api/save-measurement.php`, {
        method:  'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
        body:    JSON.stringify(payload),
      })
        .then(r => r.json())
        .then(res => {
          if (res.success && measureLayers[id]) {
            // Refresh label text on map
            const entry = measureLayers[id];
            const displayFt = res.display_ft ?? calc_ft;
            const newLabel  = (label ? `${label}: ` : '') + (displayFt ? `${parseFloat(displayFt).toFixed(1)} ft` : '');
            entry.labelMarker.setIcon(L.divIcon({
              className: 'sp-meas-label',
              html: `<div style="color:${color};font-size:11px;font-weight:700;white-space:nowrap;
                           background:rgba(0,0,0,.55);padding:1px 4px;border-radius:3px;">
                       ${escHtml(newLabel)}
                     </div>`,
            }));
            entry.line.setStyle({ color });
            entry.markerA.setStyle({ color });
            entry.markerB.setStyle({ color });
            showToast('Measurement updated ✓');
          }
        });
    });

    document.getElementById('deleteMeasurementBtn')?.addEventListener('click', () => {
      const id = parseInt(document.getElementById('measId').value) || 0;
      if (!id || !confirm('Delete this measurement?')) return;
      fetch(`${D.baseUrl}/admin/site-plans/api/delete-object.php?id=${id}&project_id=${D.projectId}`, {
        method: 'DELETE',
        headers: { 'X-CSRF-Token': getCsrfToken() },
      }).then(() => {
        if (measureLayers[id]) {
          const { line, markerA, markerB, labelMarker } = measureLayers[id];
          [line, markerA, markerB, labelMarker].forEach(l => l.remove());
          delete measureLayers[id];
        }
        document.getElementById('measurementPanel').style.display = 'none';
        document.getElementById('noObjectSelected').style.display = 'block';
        showToast('Measurement deleted');
      });
    });

    // Wait for layer groups then render
    const wait = setInterval(() => {
      if (window.SitePlanLayers) {
        clearInterval(wait);
        renderAllMeasurements();
      }
    }, 80);
  });

  // ── Utility helpers ────────────────────────────────────────────────────────

  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function getCsrfToken() {
    return document.querySelector('[name="csrf_token"]')?.value || '';
  }

  function showToast(msg, dur) { window.showToast?.(msg, dur); }

  // ── Expose ─────────────────────────────────────────────────────────────────
  window.SitePlanMeasure = {
    addPoint: addPoint2,
    renderMeasurement,
    renderAllMeasurements,
    selectMeasurement,
  };

})();
