/**
 * map-init.js
 * Initializes the Leaflet map, basemap tiles, and map-level event handling.
 * Exposes window.SitePlanMap as the shared map instance.
 */

(function () {
  'use strict';

  const D = SITE_PLAN_DATA;
  const ms = D.mapSettings || {};

  // ── Tile layer definitions ──────────────────────────────────────────────────
  const TILE_LAYERS = {
    esri: L.tileLayer(
      'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      {
        attribution: 'Tiles &copy; Esri &mdash; Source: Esri, i-cubed, USDA, USGS, AEX, GeoEye, Getmapping, Aerogrid, IGN, IGP, UPR-EGP, and the GIS User Community',
        maxZoom: 21,
        maxNativeZoom: 19,
        crossOrigin: true,
      }
    ),
    osm: L.tileLayer(
      'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19,
        crossOrigin: true,
      }
    ),
    mapbox: null,   // built below if token present
    google: null,   // built below if key present
  };

  if (D.mapboxToken) {
    TILE_LAYERS.mapbox = L.tileLayer(
      `https://api.mapbox.com/styles/v1/mapbox/satellite-v9/tiles/{z}/{x}/{y}?access_token=${D.mapboxToken}`,
      {
        tileSize: 512,
        zoomOffset: -1,
        attribution: '&copy; <a href="https://www.mapbox.com/">Mapbox</a>',
        maxZoom: 22,
        crossOrigin: true,
      }
    );
  }

  if (D.googleKey) {
    TILE_LAYERS.google = L.tileLayer(
      `https://mt1.google.com/vt/lyrs=s&x={x}&y={y}&z={z}&key=${D.googleKey}`,
      { attribution: '&copy; Google Maps', maxZoom: 21, crossOrigin: true }
    );
  }

  // ── Initialize map ──────────────────────────────────────────────────────────
  const map = L.map('map', {
    center:     [ms.center_lat || 46.6021, ms.center_lng || -120.5059],
    zoom:       ms.zoom_level  || 18,
    zoomControl: false,
    attributionControl: true,
  });

  // Add scale bar
  L.control.scale({ imperial: true, metric: false, position: 'bottomleft' }).addTo(map);

  // Add zoom control top-right so toolbar buttons also work
  L.control.zoom({ position: 'bottomright' }).addTo(map);

  // Apply initial basemap
  let currentBasemap = ms.basemap || 'esri';
  function applyBasemap(name) {
    Object.values(TILE_LAYERS).forEach(t => { if (t && map.hasLayer(t)) map.removeLayer(t); });
    const layer = TILE_LAYERS[name] || TILE_LAYERS.esri;
    if (layer) {
      layer.addTo(map);
      currentBasemap = name;
    }
  }
  applyBasemap(currentBasemap);

  // ── Toolbar zoom buttons ────────────────────────────────────────────────────
  document.getElementById('zoomInBtn')?.addEventListener('click',  () => map.zoomIn());
  document.getElementById('zoomOutBtn')?.addEventListener('click', () => map.zoomOut());
  document.getElementById('fitBoundsBtn')?.addEventListener('click', () => {
    if (window.SitePlanLayers?.parcelLayer) {
      map.fitBounds(window.SitePlanLayers.parcelLayer.getBounds(), { padding: [40, 40] });
    }
  });

  // ── Basemap selector ────────────────────────────────────────────────────────
  document.getElementById('basemapSelect')?.addEventListener('change', function () {
    applyBasemap(this.value);
    saveMapSettings();
  });

  // ── Geocoding (Nominatim, no API key required) ──────────────────────────────
  document.getElementById('geocodeBtn')?.addEventListener('click', geocodeAddress);
  document.getElementById('geocodeInput')?.addEventListener('keydown', e => {
    if (e.key === 'Enter') geocodeAddress();
  });

  function geocodeAddress() {
    const input = document.getElementById('geocodeInput');
    const q = input?.value?.trim();
    if (!q) return;
    fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1`)
      .then(r => r.json())
      .then(results => {
        if (results.length) {
          const { lat, lon } = results[0];
          map.setView([parseFloat(lat), parseFloat(lon)], 18);
          showToast(`Moved to: ${results[0].display_name.split(',').slice(0,3).join(',')}`);
          saveMapSettings();
        } else {
          showToast('Address not found — try a different search', 3000, 'warning');
        }
      })
      .catch(() => showToast('Geocoding unavailable', 3000, 'warning'));
  }

  // ── Auto-save viewport on move end ─────────────────────────────────────────
  let saveTimer = null;
  map.on('moveend zoomend', () => {
    clearTimeout(saveTimer);
    saveTimer = setTimeout(saveMapSettings, 1200);
  });

  function saveMapSettings() {
    const center = map.getCenter();
    const payload = {
      project_id:  D.projectId,
      center_lat:  center.lat,
      center_lng:  center.lng,
      zoom_level:  map.getZoom(),
      rotation:    0,
      basemap:     currentBasemap,
      csrf_token:  getCsrfToken(),
    };
    if (window._calibrationPx) payload.calibration_px = window._calibrationPx;
    if (window._calibrationFt) payload.calibration_ft = window._calibrationFt;

    fetch(`${D.baseUrl}/admin/site-plans/api/save-map-settings.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
      body: JSON.stringify(payload),
    })
      .then(r => r.json())
      .then(res => {
        if (res.scale_factor) {
          window._scaleFactor = res.scale_factor;
          document.getElementById('calibrationInfo').innerHTML =
            `<span class="text-success">${parseFloat(res.scale_factor).toFixed(4)} ft/px</span>`;
        }
      });
  }

  // ── Keyboard shortcuts ──────────────────────────────────────────────────────
  document.addEventListener('keydown', e => {
    if (e.target.matches('input,textarea,select')) return;
    const tool = { v: 'select', h: 'pan', s: 'structure', m: 'measure', p: 'polygon', l: 'line', t: 'text' }[e.key.toLowerCase()];
    if (tool) {
      document.querySelectorAll('.tool-btn').forEach(b => b.classList.remove('active'));
      document.querySelector(`.tool-btn[data-tool="${tool}"]`)?.classList.add('active');
      window.SitePlanTools?.setTool(tool);
    }
    if (e.key === '+' || e.key === '=') map.zoomIn();
    if (e.key === '-') map.zoomOut();
    if (e.key === 'Escape') window.SitePlanTools?.setTool('select');
    if ((e.ctrlKey || e.metaKey) && e.key === 's') {
      e.preventDefault();
      document.getElementById('saveAllBtn')?.click();
    }
    if (e.key === 'Delete' || e.key === 'Backspace') {
      if (window.SitePlanStructures?.deleteSelected) window.SitePlanStructures.deleteSelected();
    }
  });

  // ── Save All button ─────────────────────────────────────────────────────────
  document.getElementById('saveAllBtn')?.addEventListener('click', () => {
    saveMapSettings();
    window.SitePlanStructures?.saveSelected();
    setSaveStatus('Saved ✓', 2000);
  });

  // ── Toast helper ────────────────────────────────────────────────────────────
  function showToast(msg, duration = 3000) {
    const el = document.getElementById('mapToast');
    const msgEl = document.getElementById('mapToastMsg');
    if (!el) return;
    msgEl.textContent = msg;
    el.style.display = 'block';
    clearTimeout(el._timer);
    el._timer = setTimeout(() => { el.style.display = 'none'; }, duration);
  }

  function setSaveStatus(msg, duration = 2000) {
    const el = document.getElementById('saveStatus');
    if (!el) return;
    el.textContent = msg;
    setTimeout(() => { el.textContent = ''; }, duration);
  }

  // ── CSRF token ──────────────────────────────────────────────────────────────
  function getCsrfToken() {
    return document.querySelector('[name="csrf_token"]')?.value
        || document.cookie.match(/csrf_token=([^;]+)/)?.[1]
        || '';
  }

  // ── Scale calibration UI ────────────────────────────────────────────────────
  document.getElementById('calibrateBtn')?.addEventListener('click', () => {
    const panel = document.getElementById('calibrationPanel');
    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
  });
  document.getElementById('cancelCalibration')?.addEventListener('click', () => {
    document.getElementById('calibrationPanel').style.display = 'none';
    window.SitePlanTools?.cancelCalibration();
  });
  document.getElementById('drawCalibrationLine')?.addEventListener('click', () => {
    window.SitePlanTools?.startCalibration();
    document.getElementById('calibrationStatus').textContent = 'Click two points on the map…';
  });
  document.getElementById('applyCalibration')?.addEventListener('click', () => {
    const ftInput = document.getElementById('calibrationFt');
    const ft = parseFloat(ftInput.value);
    if (!ft || ft <= 0) {
      alert('Enter the known real-world distance in feet.');
      return;
    }
    window.SitePlanTools?.applyCalibration(ft);
    document.getElementById('calibrationPanel').style.display = 'none';
  });

  // Restore calibration from saved settings
  if (ms.scale_factor) {
    window._scaleFactor = parseFloat(ms.scale_factor);
    window._calibrationPx = ms.calibration_px ? parseFloat(ms.calibration_px) : null;
    window._calibrationFt = ms.calibration_ft ? parseFloat(ms.calibration_ft) : null;
  }

  // ── Expose globally ─────────────────────────────────────────────────────────
  window.SitePlanMap = map;
  window.showToast   = showToast;
  window.setSaveStatus = setSaveStatus;
  window.getCsrfToken  = getCsrfToken;
  window.saveMapSettings = saveMapSettings;
  window.applyBasemap  = applyBasemap;

})();
