/**
 * structure-tools.js
 * Handles placing, rendering, selecting, moving, and saving
 * structures and drawn objects on the Leaflet map.
 */

(function () {
  'use strict';

  const D = SITE_PLAN_DATA;

  let selectedLeafletLayer = null;  // currently selected Leaflet layer
  let selectedObjectData   = null;  // its raw data object

  // Color map by structure type
  const TYPE_COLORS = {
    house:      { fill: '#AED6F1', border: '#1A5276' },
    adu:        { fill: '#A9DFBF', border: '#1E8449' },
    garage:     { fill: '#D5D8DC', border: '#566573' },
    shop:       { fill: '#FAD7A0', border: '#784212' },
    shed:       { fill: '#D2B4DE', border: '#6C3483' },
    deck:       { fill: '#FDEBD0', border: '#6E2F1A' },
    patio:      { fill: '#FDEBD0', border: '#6E2F1A' },
    well:       { fill: '#85C1E9', border: '#1A5276' },
    septic:     { fill: '#A9CCE3', border: '#1A5276' },
    drain_field:{ fill: '#ABEBC6', border: '#1E8449' },
    driveway:   { fill: '#CCD1D1', border: '#717D7E' },
    carport:    { fill: '#D5D8DC', border: '#566573' },
    meter:      { fill: '#F9E79F', border: '#784212' },
    water_line: { fill: '#5DADE2', border: '#1A5276' },
    sewer_line: { fill: '#A569BD', border: '#6C3483' },
    power_line: { fill: '#F4D03F', border: '#784212' },
    setback:    { fill: 'transparent', border: '#E74C3C' },
    default:    { fill: '#4A90D9', border: '#1A5276' },
  };

  // Map from layer name to the correct groups bucket
  function getGroupForObject(obj) {
    const groups = window.SitePlanLayers;
    if (!groups) return null;
    if (obj.layer === 'setbacks')   return groups.setbacks;
    if (obj.layer === 'utilities')  return groups.utilities;
    if (obj.layer === 'driveways')  return groups.driveways;
    if (obj.layer === 'drainage')   return groups.drainage;
    if (obj.layer === 'labels')     return groups.labels;
    if (obj.layer === 'parcel')     return groups.parcel;
    if (obj.status === 'existing')  return groups.existing;
    return groups.proposed;
  }

  // ── Render all saved objects from DB ─────────────────────────────────────
  function renderAllObjects() {
    (D.objects || []).forEach(obj => renderObject(obj));
  }

  function renderObject(obj) {
    const coords = typeof obj.coordinates === 'string'
      ? JSON.parse(obj.coordinates) : (obj.coordinates || []);

    if (!coords || coords.length < 2) return;

    const colors  = TYPE_COLORS[obj.structure_type] || TYPE_COLORS.default;
    const fillClr = obj.fill_color   || colors.fill;
    const brdClr  = obj.border_color || colors.border;
    const opacity = obj.fill_opacity != null ? parseFloat(obj.fill_opacity) : 0.4;

    let leafletLayer;

    if (obj.object_type === 'rect' || obj.object_type === 'polygon' ||
        obj.object_type === 'driveway' || obj.object_type === 'parking') {
      const latLngs = coords.map(c => [c.lat, c.lng]);
      leafletLayer = L.polygon(latLngs, {
        color:       brdClr,
        weight:      obj.border_width || 2,
        fillColor:   fillClr,
        fillOpacity: opacity,
        dashArray:   obj.status === 'proposed' ? '6 4' : null,
      });

    } else if (obj.object_type === 'line' || obj.object_type === 'arrow' ||
               obj.object_type === 'utility' || obj.object_type === 'setback') {
      const latLngs = coords.map(c => [c.lat, c.lng]);
      leafletLayer = L.polyline(latLngs, {
        color:  brdClr,
        weight: obj.border_width || 2,
        dashArray: obj.object_type === 'setback' ? '8 4' : null,
      });

    } else if (obj.object_type === 'text') {
      const first = coords[0];
      leafletLayer = L.marker([first.lat, first.lng], {
        icon: L.divIcon({
          className: 'sp-text-label',
          html: `<div style="color:${brdClr};font-size:${obj.font_size||12}px;font-weight:600;white-space:nowrap;">${escHtml(obj.label || '')}</div>`,
          iconAnchor: [0, 0],
        }),
        draggable: true,
      });
    }

    if (!leafletLayer) return;

    // Store data reference on the layer
    leafletLayer._spData = obj;

    // Bind popup/tooltip label
    if (obj.label) {
      const statusBadge = obj.status === 'existing' ? '🔵' : '🟢';
      const sqftInfo = obj.sqft ? ` · ${Math.round(obj.sqft)} sq ft` : '';
      leafletLayer.bindTooltip(
        `<strong>${escHtml(obj.label)}</strong>${sqftInfo} ${statusBadge}`,
        { sticky: true, className: 'sp-tooltip' }
      );
    }

    // Click handler → select object
    leafletLayer.on('click', function (e) {
      L.DomEvent.stopPropagation(e);
      selectObject(this, this._spData);
    });

    // Draggable for polygons via a center marker
    if (obj.object_type !== 'text') {
      makeDraggable(leafletLayer, obj);
    }

    // Add to appropriate layer group
    const group = getGroupForObject(obj);
    if (group) leafletLayer.addTo(group);
    else leafletLayer.addTo(window.SitePlanMap);

    return leafletLayer;
  }

  // ── Drag support for polygon/polyline objects ─────────────────────────────
  function makeDraggable(layer, obj) {
    if (!layer.getBounds) return;
    const center = layer.getBounds().getCenter();
    const dragHandle = L.circleMarker(center, {
      radius:      6,
      color:       '#fff',
      fillColor:   '#E74C3C',
      fillOpacity: 0.9,
      weight:      2,
      draggable:   true,
      className:   'sp-drag-handle',
    });
    dragHandle._isDragHandle = true;
    dragHandle.on('click', function (e) {
      L.DomEvent.stopPropagation(e);
      selectObject(layer, obj);
    });

    let startCenter = null;
    let startCoords = null;

    dragHandle.on('mousedown', function () {
      window.SitePlanMap.dragging.disable();
      startCenter = dragHandle.getLatLng();
      startCoords = obj.coordinates ? (typeof obj.coordinates === 'string'
        ? JSON.parse(obj.coordinates) : obj.coordinates) : [];
    });

    dragHandle.on('drag', function () {
      const newCenter = dragHandle.getLatLng();
      if (!startCenter || !startCoords.length) return;
      const dLat = newCenter.lat - startCenter.lat;
      const dLng = newCenter.lng - startCenter.lng;
      const newCoords = startCoords.map(c => ({ lat: c.lat + dLat, lng: c.lng + dLng }));
      const newLatLngs = newCoords.map(c => [c.lat, c.lng]);
      if (layer.setLatLngs) layer.setLatLngs(newLatLngs);
    });

    dragHandle.on('dragend', function () {
      window.SitePlanMap.dragging.enable();
      const newCenter = dragHandle.getLatLng();
      if (!startCenter || !startCoords.length) return;
      const dLat = newCenter.lat - startCenter.lat;
      const dLng = newCenter.lng - startCenter.lng;
      obj.coordinates = startCoords.map(c => ({ lat: c.lat + dLat, lng: c.lng + dLng }));
      // Recalculate anchor
      if (layer.getBounds) {
        const c = layer.getBounds().getCenter();
        obj.anchor_lat = c.lat;
        obj.anchor_lng = c.lng;
      }
      autoSaveObject(obj);
      startCenter = dragHandle.getLatLng();
      startCoords = obj.coordinates;
    });

    const group = getGroupForObject(obj);
    if (group) dragHandle.addTo(group);
    layer._dragHandle = dragHandle;
  }

  // ── Select object → populate right panel ─────────────────────────────────
  function selectObject(leafletLayer, data) {
    deselectCurrent();
    selectedLeafletLayer = leafletLayer;
    selectedObjectData   = data;

    // Highlight
    if (leafletLayer.setStyle) {
      leafletLayer.setStyle({ weight: 4, color: '#FFD700' });
    }

    // Populate right panel
    document.getElementById('noObjectSelected').style.display  = 'none';
    document.getElementById('objectProperties').style.display  = 'block';
    document.getElementById('measurementPanel').style.display  = 'none';

    document.getElementById('objId').value             = data.id || '';
    document.getElementById('objLabel').value          = data.label || '';
    document.getElementById('objStructureType').value  = data.structure_type || '';
    document.getElementById('objStatus').value         = data.status || 'proposed';
    document.getElementById('objWidth').value          = data.width_ft  || '';
    document.getElementById('objLength').value         = data.length_ft || '';
    document.getElementById('objHeight').value         = data.height_ft || '';
    document.getElementById('objSqft').value           = data.sqft ? Math.round(data.sqft) : '';
    document.getElementById('objRotation').value       = data.rotation_deg || 0;
    document.getElementById('objFillColor').value      = rgbToHex(data.fill_color   || '#4A90D9');
    document.getElementById('objBorderColor').value    = rgbToHex(data.border_color || '#1A5276');
    const opVal = data.fill_opacity != null ? parseFloat(data.fill_opacity) : 0.4;
    document.getElementById('objFillOpacity').value    = opVal;
    document.getElementById('objFillOpacityVal').textContent = opVal.toFixed(2);
    document.getElementById('objNotes').value          = data.notes || '';
  }

  function deselectCurrent() {
    if (selectedLeafletLayer) {
      const d = selectedObjectData;
      const colors = TYPE_COLORS[d?.structure_type] || TYPE_COLORS.default;
      if (selectedLeafletLayer.setStyle) {
        selectedLeafletLayer.setStyle({
          weight: d?.border_width || 2,
          color:  d?.border_color || colors.border,
        });
      }
    }
    selectedLeafletLayer = null;
    selectedObjectData   = null;
    document.getElementById('noObjectSelected').style.display  = 'block';
    document.getElementById('objectProperties').style.display  = 'none';
  }

  // ── Save selected object properties ──────────────────────────────────────
  function saveSelectedObject() {
    if (!selectedObjectData) return;
    const d = selectedObjectData;

    d.label          = document.getElementById('objLabel').value;
    d.structure_type = document.getElementById('objStructureType').value;
    d.status         = document.getElementById('objStatus').value;
    d.width_ft       = parseFloat(document.getElementById('objWidth').value)  || null;
    d.length_ft      = parseFloat(document.getElementById('objLength').value) || null;
    d.height_ft      = parseFloat(document.getElementById('objHeight').value) || null;
    d.rotation_deg   = parseFloat(document.getElementById('objRotation').value) || 0;
    d.fill_color     = document.getElementById('objFillColor').value;
    d.border_color   = document.getElementById('objBorderColor').value;
    d.fill_opacity   = parseFloat(document.getElementById('objFillOpacity').value);
    d.notes          = document.getElementById('objNotes').value;
    if (d.width_ft && d.length_ft) d.sqft = d.width_ft * d.length_ft;

    // Update tooltip
    if (d.label && selectedLeafletLayer.setTooltipContent) {
      const sqftInfo = d.sqft ? ` · ${Math.round(d.sqft)} sq ft` : '';
      selectedLeafletLayer.setTooltipContent(`<strong>${escHtml(d.label)}</strong>${sqftInfo}`);
    }
    // Update style
    if (selectedLeafletLayer.setStyle) {
      selectedLeafletLayer.setStyle({
        fillColor:   d.fill_color,
        color:       d.border_color,
        fillOpacity: d.fill_opacity,
        weight:      d.border_width || 2,
      });
    }

    autoSaveObject(d);
    document.getElementById('objSqft').value = d.sqft ? Math.round(d.sqft) : '';
    showToast('Object saved ✓');
  }

  function autoSaveObject(data) {
    const payload = Object.assign({}, data, {
      project_id: D.projectId,
      csrf_token: getCsrfToken(),
    });
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
        if (res.id && !data.id) {
          data.id = res.id;
          document.getElementById('objId').value = res.id;
        }
      })
      .catch(err => console.error('save-object error', err));
  }

  // ── Delete selected object ────────────────────────────────────────────────
  function deleteSelected() {
    if (!selectedObjectData) return;
    if (!confirm(`Delete "${selectedObjectData.label || 'this object'}"?`)) return;

    const id = selectedObjectData.id;
    if (id) {
      fetch(`${D.baseUrl}/admin/site-plans/api/delete-object.php?id=${id}&project_id=${D.projectId}`, {
        method:  'DELETE',
        headers: { 'X-CSRF-Token': getCsrfToken() },
      }).catch(() => {});
    }

    // Remove from map
    if (selectedLeafletLayer._dragHandle) {
      selectedLeafletLayer._dragHandle.remove();
    }
    selectedLeafletLayer.remove();
    deselectCurrent();
    showToast('Object deleted');
  }

  // ── Quick-add structure buttons ───────────────────────────────────────────
  document.querySelectorAll('.quick-struct').forEach(btn => {
    btn.addEventListener('click', function () {
      const type   = this.dataset.type;
      const label  = this.dataset.label;
      const color  = this.dataset.color;
      const status = this.dataset.status;
      // Switch to structure tool and prime it
      window.SitePlanTools?.setTool('structure', { type, label, color, status });
    });
  });

  // ── Right panel wire-up ───────────────────────────────────────────────────
  document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('saveObjectBtn')?.addEventListener('click', saveSelectedObject);
    document.getElementById('deleteObjectBtn')?.addEventListener('click', deleteSelected);
    document.getElementById('deselectObjectBtn')?.addEventListener('click', deselectCurrent);

    // Live opacity display
    document.getElementById('objFillOpacity')?.addEventListener('input', function () {
      document.getElementById('objFillOpacityVal').textContent = parseFloat(this.value).toFixed(2);
    });

    // Auto-calculate sqft
    ['objWidth', 'objLength'].forEach(id => {
      document.getElementById(id)?.addEventListener('input', () => {
        const w = parseFloat(document.getElementById('objWidth').value)  || 0;
        const l = parseFloat(document.getElementById('objLength').value) || 0;
        document.getElementById('objSqft').value = w && l ? Math.round(w * l) : '';
      });
    });

    // Close selection on map click (empty area)
    window.SitePlanMap?.on('click', () => {
      if (!window._drawingActive) deselectCurrent();
    });

    // Render all objects from DB
    const waitForLayers = setInterval(() => {
      if (window.SitePlanLayers) {
        clearInterval(waitForLayers);
        renderAllObjects();
      }
    }, 50);
  });

  // ── Utility helpers ───────────────────────────────────────────────────────
  function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
  }

  function rgbToHex(color) {
    if (!color || color.startsWith('#')) return color || '#000000';
    return color;
  }

  function getCsrfToken() {
    return document.querySelector('[name="csrf_token"]')?.value || '';
  }

  // ── Expose globally ───────────────────────────────────────────────────────
  window.SitePlanStructures = {
    renderObject,
    selectObject,
    deselectCurrent,
    saveSelected:  saveSelectedObject,
    deleteSelected,
    autoSaveObject,
    TYPE_COLORS,
    getGroupForObject,
  };

})();
