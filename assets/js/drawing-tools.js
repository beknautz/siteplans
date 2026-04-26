/**
 * drawing-tools.js
 * Tool mode switcher + draw handlers for polygon, line, text, setback,
 * parcel boundary, and scale-calibration line.
 */

(function () {
  'use strict';

  const D = SITE_PLAN_DATA;

  let currentTool    = 'select';
  let pendingStructOpts = null;   // primed by quick-add buttons
  let drawingPoints  = [];        // lat/lng points during polygon/line draw
  let drawPreview    = null;      // live preview polyline
  let drawMarkers    = [];        // vertex click markers
  let calibPoints    = [];        // two points for calibration line
  let calibLine      = null;

  // ── Tool mode management ────────────────────────────────────────────────────

  function setTool(name, opts) {
    // Cancel any in-progress drawing
    cancelDraw();
    currentTool = name;
    pendingStructOpts = opts || null;
    window._drawingActive = (name !== 'select' && name !== 'pan');

    // Update toolbar button states
    document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
    document.querySelector(`.tool-btn[data-tool="${name}"]`)?.classList.add('active');

    const map = window.SitePlanMap;
    if (!map) return;

    if (name === 'pan' || name === 'select') {
      map.dragging.enable();
      map.getContainer().style.cursor = name === 'pan' ? 'grab' : 'default';
      map.off('click', onMapClick);
    } else {
      map.dragging.enable();   // keep pan active so user can still navigate
      map.getContainer().style.cursor = 'crosshair';
      map.on('click', onMapClick);
    }

    if (name === 'structure' && pendingStructOpts) {
      showToast(`Click map to place ${pendingStructOpts.label || 'structure'}. Right-click to cancel.`);
    } else if (name === 'polygon' || name === 'parcel') {
      showToast('Click to add vertices. Double-click to close polygon.');
    } else if (name === 'line' || name === 'setback') {
      showToast('Click start point. Click end point. Double-click to finish multi-segment line.');
    } else if (name === 'text') {
      showToast('Click map to place text label.');
    } else if (name === 'measure') {
      showToast('Click start point, then end point to measure distance.');
    }
  }

  // ── Map click handler (dispatch to active tool) ─────────────────────────────

  function onMapClick(e) {
    switch (currentTool) {
      case 'structure': placeStructure(e.latlng);   break;
      case 'polygon':   addPolygonPoint(e.latlng);  break;
      case 'line':      addLinePoint(e.latlng);     break;
      case 'setback':   addSetbackPoint(e.latlng);  break;
      case 'parcel':    addParcelPoint(e.latlng);   break;
      case 'text':      placeTextLabel(e.latlng);   break;
      case 'measure':   window.SitePlanMeasure?.addPoint(e.latlng); break;
    }
  }

  // ── Right-click cancels drawing ─────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', () => {
    const map = window.SitePlanMap;
    if (!map) return;
    map.getContainer().addEventListener('contextmenu', e => {
      e.preventDefault();
      cancelDraw();
    });
    // Double-click closes polygon/line
    map.on('dblclick', e => {
      L.DomEvent.stopPropagation(e);
      finishDraw();
    });
  });

  function cancelDraw() {
    drawingPoints = [];
    drawMarkers.forEach(m => m.remove());
    drawMarkers = [];
    if (drawPreview) { drawPreview.remove(); drawPreview = null; }
    window._drawingActive = false;
  }

  // ── Live preview line (follows mouse) ──────────────────────────────────────

  function startPreview() {
    const map = window.SitePlanMap;
    if (!map) return;
    map.on('mousemove', onMouseMove);
  }

  function stopPreview() {
    window.SitePlanMap?.off('mousemove', onMouseMove);
    if (drawPreview) { drawPreview.remove(); drawPreview = null; }
  }

  function onMouseMove(e) {
    if (!drawingPoints.length) return;
    const pts = [...drawingPoints.map(p => [p.lat, p.lng]), [e.latlng.lat, e.latlng.lng]];
    if (!drawPreview) {
      drawPreview = L.polyline(pts, { color: '#F39C12', weight: 2, dashArray: '4 4' })
        .addTo(window.SitePlanMap);
    } else {
      drawPreview.setLatLngs(pts);
    }
  }

  // ── STRUCTURE placement (single click → default 30×40 ft rectangle) ────────

  function placeStructure(latlng) {
    const opts = pendingStructOpts || { type: 'house', label: 'New Structure', color: '#AED6F1', status: 'proposed' };
    const colors = window.SitePlanStructures?.TYPE_COLORS[opts.type] || { fill: opts.color, border: '#333' };

    // Default 30ft × 40ft rectangle around click point (approx deg)
    const metersPerFt = 0.3048;
    const degPerMeterLat = 1 / 111320;
    const degPerMeterLng = 1 / (111320 * Math.cos(latlng.lat * Math.PI / 180));

    const halfW = (20 * metersPerFt) * degPerMeterLng;
    const halfH = (15 * metersPerFt) * degPerMeterLat;

    const coords = [
      { lat: latlng.lat + halfH, lng: latlng.lng - halfW },
      { lat: latlng.lat + halfH, lng: latlng.lng + halfW },
      { lat: latlng.lat - halfH, lng: latlng.lng + halfW },
      { lat: latlng.lat - halfH, lng: latlng.lng - halfW },
    ];

    const obj = {
      project_id:     D.projectId,
      layer:          'structures',
      object_type:    'rect',
      structure_type: opts.type,
      label:          opts.label || capitalise(opts.type),
      status:         opts.status || 'proposed',
      geometry_type:  'latlng',
      coordinates:    coords,
      anchor_lat:     latlng.lat,
      anchor_lng:     latlng.lng,
      width_ft:       40,
      length_ft:      30,
      sqft:           1200,
      rotation_deg:   0,
      fill_color:     colors.fill,
      fill_opacity:   0.5,
      border_color:   colors.border,
      border_width:   2,
    };

    // Save first, then render with returned ID
    saveNewObject(obj, newObj => {
      window.SitePlanStructures?.renderObject(newObj);
      showToast(`${newObj.label} placed — click to select and edit properties`);
    });

    setTool('select');
  }

  // ── POLYGON drawing ─────────────────────────────────────────────────────────

  function addPolygonPoint(latlng) {
    drawingPoints.push(latlng);
    const marker = L.circleMarker([latlng.lat, latlng.lng], {
      radius: 5, color: '#F39C12', fillColor: '#fff', fillOpacity: 1, weight: 2,
    }).addTo(window.SitePlanMap);
    drawMarkers.push(marker);
    if (drawingPoints.length === 1) startPreview();
  }

  // ── LINE drawing ────────────────────────────────────────────────────────────

  function addLinePoint(latlng) {
    drawingPoints.push(latlng);
    const marker = L.circleMarker([latlng.lat, latlng.lng], {
      radius: 4, color: '#3498DB', fillColor: '#fff', fillOpacity: 1, weight: 2,
    }).addTo(window.SitePlanMap);
    drawMarkers.push(marker);
    if (drawingPoints.length === 1) startPreview();
  }

  // ── SETBACK LINE drawing ────────────────────────────────────────────────────

  function addSetbackPoint(latlng) {
    drawingPoints.push(latlng);
    const marker = L.circleMarker([latlng.lat, latlng.lng], {
      radius: 4, color: '#E74C3C', fillColor: '#fff', fillOpacity: 1, weight: 2,
    }).addTo(window.SitePlanMap);
    drawMarkers.push(marker);
    if (drawingPoints.length === 1) startPreview();
  }

  // ── PARCEL BOUNDARY drawing ─────────────────────────────────────────────────

  function addParcelPoint(latlng) {
    drawingPoints.push(latlng);
    const marker = L.circleMarker([latlng.lat, latlng.lng], {
      radius: 5, color: '#8E44AD', fillColor: '#fff', fillOpacity: 1, weight: 2,
    }).addTo(window.SitePlanMap);
    drawMarkers.push(marker);
    if (drawingPoints.length === 1) startPreview();
  }

  // ── TEXT LABEL placement ────────────────────────────────────────────────────

  function placeTextLabel(latlng) {
    const text = prompt('Enter label text:');
    if (!text) return;
    const obj = {
      project_id:    D.projectId,
      layer:         'labels',
      object_type:   'text',
      label:         text,
      status:        'existing',
      geometry_type: 'latlng',
      coordinates:   [{ lat: latlng.lat, lng: latlng.lng }],
      anchor_lat:    latlng.lat,
      anchor_lng:    latlng.lng,
      border_color:  '#111111',
      fill_color:    'transparent',
      font_size:     13,
    };
    saveNewObject(obj, newObj => {
      window.SitePlanStructures?.renderObject(newObj);
      showToast('Label placed');
    });
    setTool('select');
  }

  // ── Finish polygon / line on double-click ───────────────────────────────────

  function finishDraw() {
    stopPreview();
    if (drawingPoints.length < 2) {
      cancelDraw();
      return;
    }

    const coords = drawingPoints.map(p => ({ lat: p.lat, lng: p.lng }));
    let obj = null;

    if (currentTool === 'polygon') {
      obj = {
        project_id:    D.projectId,
        layer:         'structures',
        object_type:   'polygon',
        label:         'Polygon',
        status:        'proposed',
        geometry_type: 'latlng',
        coordinates:   coords,
        fill_color:    '#AED6F1',
        fill_opacity:  0.4,
        border_color:  '#1A5276',
        border_width:  2,
      };
    } else if (currentTool === 'line') {
      obj = {
        project_id:    D.projectId,
        layer:         'structures',
        object_type:   'line',
        label:         'Line',
        status:        'existing',
        geometry_type: 'latlng',
        coordinates:   coords,
        fill_color:    'transparent',
        border_color:  '#333333',
        border_width:  2,
      };
    } else if (currentTool === 'setback') {
      obj = {
        project_id:    D.projectId,
        layer:         'setbacks',
        object_type:   'setback',
        label:         'Setback',
        status:        'existing',
        geometry_type: 'latlng',
        coordinates:   coords,
        fill_color:    'transparent',
        border_color:  '#E74C3C',
        border_width:  2,
      };
    } else if (currentTool === 'parcel') {
      saveParcelBoundary(coords);
      cancelDraw();
      setTool('select');
      return;
    }

    if (obj) {
      saveNewObject(obj, newObj => {
        window.SitePlanStructures?.renderObject(newObj);
        showToast(`${newObj.object_type} saved`);
      });
    }

    cancelDraw();
    setTool('select');
  }

  // ── Parcel boundary save ────────────────────────────────────────────────────

  function saveParcelBoundary(coords) {
    const geojson = {
      type: 'FeatureCollection',
      features: [{
        type: 'Feature',
        geometry: { type: 'Polygon', coordinates: [coords.map(c => [c.lng, c.lat])] },
        properties: {},
      }],
    };
    fetch(`${D.baseUrl}/admin/site-plans/api/save-parcel.php`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
      body: JSON.stringify({
        project_id:    D.projectId,
        geojson:       JSON.stringify(geojson),
        boundary_type: 'manual',
        csrf_token:    getCsrfToken(),
      }),
    })
      .then(r => r.json())
      .then(() => {
        // Render parcel boundary
        const latLngs = coords.map(c => [c.lat, c.lng]);
        const poly = L.polygon(latLngs, {
          color: '#8E44AD', weight: 3, fillColor: 'transparent', fillOpacity: 0,
          dashArray: '10 6',
        });
        const groups = window.SitePlanLayers;
        if (groups?.parcel) {
          groups.parcel.clearLayers();
          poly.addTo(groups.parcel);
          poly.bindTooltip('Parcel Boundary', { sticky: true });
        }
        showToast('Parcel boundary saved');
        // Expose for fit-bounds
        window.SitePlanLayers.parcelLayer = poly;
      });
  }

  // ── Apply Setbacks overlay from rules ──────────────────────────────────────

  document.getElementById('applySetbacksBtn')?.addEventListener('click', () => {
    const parcelLayer = window.SitePlanLayers?.parcelLayer;
    if (!parcelLayer) {
      showToast('Draw the parcel boundary first, then apply setbacks.', 4000);
      return;
    }

    const front  = parseFloat(document.querySelector('[name="front_ft"]')?.value)    || 20;
    const rear   = parseFloat(document.querySelector('[name="rear_ft"]')?.value)     || 5;
    const side   = parseFloat(document.querySelector('[name="side_ft"]')?.value)     || 5;

    // Approximate setback inset using Turf.js if available
    if (window.turf) {
      const gj = parcelLayer.toGeoJSON();
      // Convert feet to meters for turf
      const ftToM = 0.3048;
      try {
        // Use a simple inset: shrink polygon by average setback
        const avgSetback = (front + rear + side * 2) / 4;
        const shrunk = turf.buffer(gj, -(avgSetback * ftToM) / 1000, { units: 'kilometers' });
        if (shrunk?.features?.length) {
          const setbackPoly = L.geoJSON(shrunk, {
            style: { color: '#E74C3C', weight: 2, dashArray: '8 4', fill: false },
          });
          window.SitePlanLayers.setbacks.clearLayers();
          setbackPoly.addTo(window.SitePlanLayers.setbacks);
          setbackPoly.bindTooltip(`Setbacks: F${front}' R${rear}' S${side}'`);
          showToast('Setback lines applied');
        }
      } catch (e) {
        showToast('Could not compute setbacks — check parcel boundary', 4000);
      }
    } else {
      showToast('Turf.js required for automatic setback computation.', 4000);
    }
  });

  // ── Scale Calibration tool ──────────────────────────────────────────────────

  function startCalibration() {
    calibPoints = [];
    if (calibLine) { calibLine.remove(); calibLine = null; }
    currentTool = 'calibrate';
    window._drawingActive = true;
    window.SitePlanMap?.on('click', onCalibClick);
    window.SitePlanMap?.getContainer() && (window.SitePlanMap.getContainer().style.cursor = 'crosshair');
  }

  function onCalibClick(e) {
    calibPoints.push(e.latlng);
    if (calibPoints.length === 1) {
      document.getElementById('calibrationStatus').textContent = 'Now click the second point…';
    } else if (calibPoints.length === 2) {
      window.SitePlanMap.off('click', onCalibClick);
      const p1 = calibPoints[0], p2 = calibPoints[1];
      calibLine = L.polyline([[p1.lat, p1.lng],[p2.lat, p2.lng]], {
        color: '#F39C12', weight: 3, dashArray: '6 3',
      }).addTo(window.SitePlanMap);
      // Calculate pixel distance at current zoom (screen pixels)
      const pt1 = window.SitePlanMap.latLngToContainerPoint(p1);
      const pt2 = window.SitePlanMap.latLngToContainerPoint(p2);
      const pxDist = Math.sqrt(Math.pow(pt2.x - pt1.x, 2) + Math.pow(pt2.y - pt1.y, 2));
      window._calibrationPxMeasured = pxDist;
      document.getElementById('calibrationStatus').textContent =
        `Line drawn (${Math.round(pxDist)}px). Enter real-world distance below.`;
      window.SitePlanMap.getContainer().style.cursor = 'default';
      currentTool = 'select';
      window._drawingActive = false;
    }
  }

  function applyCalibration(knownFt) {
    const px = window._calibrationPxMeasured;
    if (!px) { alert('Draw the calibration line first.'); return; }
    window._scaleFactor   = knownFt / px;
    window._calibrationPx = px;
    window._calibrationFt = knownFt;
    document.getElementById('calibrationInfo').innerHTML =
      `<span class="text-success">${window._scaleFactor.toFixed(4)} ft/px</span>`;
    if (calibLine) { calibLine.remove(); calibLine = null; }
    window.saveMapSettings?.();
    showToast(`Scale set: ${window._scaleFactor.toFixed(4)} ft/px`);
  }

  function cancelCalibration() {
    window.SitePlanMap?.off('click', onCalibClick);
    if (calibLine) { calibLine.remove(); calibLine = null; }
    calibPoints = [];
    currentTool = 'select';
    window._drawingActive = false;
    window.SitePlanMap?.getContainer() && (window.SitePlanMap.getContainer().style.cursor = 'default');
  }

  // ── Tool button clicks ──────────────────────────────────────────────────────

  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.tool-btn').forEach(btn => {
      btn.addEventListener('click', function () {
        setTool(this.dataset.tool);
      });
    });
  });

  // ── Object save helper ──────────────────────────────────────────────────────

  function saveNewObject(obj, callback) {
    const payload = Object.assign({}, obj, { csrf_token: getCsrfToken() });
    if (Array.isArray(payload.coordinates)) {
      payload.coordinates = JSON.stringify(payload.coordinates);
    }
    fetch(`${D.baseUrl}/admin/site-plans/api/save-object.php`, {
      method:  'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
      body:    JSON.stringify(payload),
    })
      .then(r => r.json())
      .then(res => {
        if (res.success) {
          obj.id = res.id;
          if (res.sqft) obj.sqft = res.sqft;
          if (Array.isArray(obj.coordinates)) {
            // keep as array for renderObject
          } else if (typeof obj.coordinates === 'string') {
            obj.coordinates = JSON.parse(obj.coordinates);
          }
          callback?.(obj);
        }
      })
      .catch(err => console.error('saveNewObject error', err));
  }

  // ── Utility ────────────────────────────────────────────────────────────────

  function capitalise(s) {
    return s ? s.charAt(0).toUpperCase() + s.slice(1) : s;
  }

  function getCsrfToken() {
    return document.querySelector('[name="csrf_token"]')?.value || '';
  }

  function showToast(msg, duration) {
    window.showToast?.(msg, duration);
  }

  // ── Expose ─────────────────────────────────────────────────────────────────
  window.SitePlanTools = {
    setTool,
    cancelCalibration,
    startCalibration,
    applyCalibration,
    finishDraw,
    cancelDraw,
  };

})();
