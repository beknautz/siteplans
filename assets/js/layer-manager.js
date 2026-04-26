/**
 * layer-manager.js
 * Manages Leaflet layer groups per layer key.
 * Handles show/hide toggle persistence and layer group registration.
 */

(function () {
  'use strict';

  const D = SITE_PLAN_DATA;

  // One FeatureGroup per logical layer
  const groups = {
    satellite:    null,   // Leaflet tile layer — visibility handled separately
    parcel:       L.featureGroup(),
    existing:     L.featureGroup(),
    proposed:     L.featureGroup(),
    setbacks:     L.featureGroup(),
    measurements: L.featureGroup(),
    utilities:    L.featureGroup(),
    labels:       L.featureGroup(),
    driveways:    L.featureGroup(),
    drainage:     L.featureGroup(),
    notes:        L.featureGroup(),
  };

  // Wait for map to be ready
  document.addEventListener('DOMContentLoaded', init);
  // map-init.js runs inline; check if map is already available
  if (document.readyState !== 'loading') setTimeout(init, 50);

  function init() {
    const map = window.SitePlanMap;
    if (!map) { setTimeout(init, 50); return; }

    // Add all feature groups to map
    Object.entries(groups).forEach(([key, group]) => {
      if (group) group.addTo(map);
    });

    // Apply initial visibility from DB
    (D.layers || []).forEach(layer => {
      if (!layer.visible && groups[layer.layer_key]) {
        groups[layer.layer_key].remove();
      }
    });

    // Wire up toggle checkboxes
    document.querySelectorAll('.layer-toggle').forEach(cb => {
      cb.addEventListener('change', function () {
        const key   = this.dataset.layer;
        const group = groups[key];
        if (!group) {
          // Satellite layer handled via basemap
          if (key === 'satellite') window.applyBasemap?.(this.checked ? (D.mapSettings?.basemap || 'esri') : 'none');
          return;
        }
        if (this.checked) {
          group.addTo(map);
        } else {
          group.remove();
        }
        saveLayerVisibility(key, this.checked ? 1 : 0);
      });
    });

    // Show All / Hide All
    document.getElementById('showAllLayers')?.addEventListener('click', () => {
      document.querySelectorAll('.layer-toggle').forEach(cb => {
        cb.checked = true;
        const g = groups[cb.dataset.layer];
        if (g && !map.hasLayer(g)) g.addTo(map);
      });
    });
    document.getElementById('hideAllLayers')?.addEventListener('click', () => {
      document.querySelectorAll('.layer-toggle').forEach(cb => {
        cb.checked = false;
        const g = groups[cb.dataset.layer];
        if (g && map.hasLayer(g)) g.remove();
      });
    });

    window.SitePlanLayers = groups;
  }

  function saveLayerVisibility(layerKey, visible) {
    fetch(`${D.baseUrl}/admin/site-plans/api/save-layer.php`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': getCsrfToken() },
      body: JSON.stringify({
        project_id: D.projectId,
        layer_key:  layerKey,
        visible:    visible,
        csrf_token: getCsrfToken(),
      }),
    }).catch(() => {});
  }

  function getCsrfToken() {
    return document.querySelector('[name="csrf_token"]')?.value || '';
  }

})();
