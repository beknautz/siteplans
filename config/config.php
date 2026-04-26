<?php
// ============================================================
// BaceBuilt LLC - Site Plan Tool Configuration
// ============================================================

define('APP_NAME',    'BaceBuilt Site Plan Tool');
define('APP_VERSION', '1.0.0');
define('COMPANY',     'BaceBuilt LLC');
define('BASE_URL',    '/siteplans');          // Change to match your web root path
define('BASE_PATH',   dirname(__DIR__));      // Absolute filesystem root

// ─── Database ───────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'bacebuilt_siteplans');
define('DB_USER', 'root');                    // Replace with your DB user
define('DB_PASS', '');                        // Replace with your DB password
define('DB_PORT', 3306);

// ─── Map API Keys ────────────────────────────────────────────
// Set MAPBOX_TOKEN for Mapbox satellite tiles (preferred)
define('MAPBOX_TOKEN',  '');  // pk.eyJ1IjoiWU9VUl9VU0VSTkFNRSIsImEiOiJ...

// Set GOOGLE_MAPS_KEY for Google Maps satellite
define('GOOGLE_MAPS_KEY', '');  // AIza...

// ESRI World Imagery is used as fallback (no key required)
define('USE_ESRI_FALLBACK', true);

// ─── PDF Export ──────────────────────────────────────────────
define('PDF_LOGO_PATH', BASE_PATH . '/assets/img/bacebuilt-logo.png');
define('EXPORTS_DIR',   BASE_PATH . '/exports');   // writable directory for PDF storage

// ─── AI Integration Placeholder ─────────────────────────────
define('AI_ENABLED',      false);
define('AI_PROVIDER',     'claude');  // 'claude' or 'openai'
define('CLAUDE_API_KEY',  '');        // sk-ant-...
define('OPENAI_API_KEY',  '');        // sk-...

// ─── Timezone ────────────────────────────────────────────────
date_default_timezone_set('America/Los_Angeles');

// ─── Error display (set false in production) ─────────────────
define('DEBUG_MODE', true);
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}
