# BaceBuilt LLC — Permit Ready Site Plan Tool

A web-based permit site plan generator for residential construction projects in Yakima County, WA and beyond. Draw structures, measure setbacks, and export permit-ready PDF sheets.

---

## Quick Start

### 1. Requirements

| Requirement | Version |
|---|---|
| PHP | 8.0 + |
| MySQL / MariaDB | 5.7 + / 10.4 + |
| Web server | Apache or Nginx |
| Composer (optional) | For PDF library |

No Node.js, no build tools required.

---

### 2. Database Setup

```bash
mysql -u root -p < setup.sql
```

This creates the `bacebuilt_siteplans` database and imports a sample project for Yakima County.

---

### 3. Configure

Edit `config/config.php`:

```php
// Database
define('DB_HOST', 'localhost');
define('DB_NAME', 'bacebuilt_siteplans');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');

// Web root path (match your server)
define('BASE_URL', '/siteplans');

// Map API keys (optional — ESRI works without any key)
define('MAPBOX_TOKEN',  'pk.eyJ1...');   // Mapbox satellite (best quality)
define('GOOGLE_MAPS_KEY', 'AIza...');    // Google Maps alternative
```

---

### 4. Web Server

**Apache** — add to `.htaccess` or vhost:
```apache
Alias /siteplans /path/to/siteplans
<Directory /path/to/siteplans>
    AllowOverride All
    Require all granted
</Directory>
```

**Nginx**:
```nginx
location /siteplans/ {
    alias /path/to/siteplans/;
    index index.php;
    try_files $uri $uri/ =404;
    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $request_filename;
    }
}
```

---

### 5. PDF Export (Optional — Better Output)

For server-side PDF generation install one of:

**TCPDF (recommended):**
```bash
composer require tecnickcom/tcpdf
```

**DomPDF:**
```bash
composer require dompdf/dompdf
```

If neither is installed, the export endpoint streams the permit sheet as HTML and you use **File → Print → Save as PDF** in the browser (works fine for production use).

---

### 6. Exports Directory

Create and make writable:
```bash
mkdir -p /path/to/siteplans/exports
chmod 755 /path/to/siteplans/exports
```

---

## File Structure

```
siteplans/
├── setup.sql                         Database schema + seed data
├── config/
│   ├── config.php                    API keys, DB credentials, settings
│   └── database.php                  PDO singleton + query helpers
├── includes/
│   ├── header.php                    Shared HTML head + navbar
│   ├── footer.php                    Shared JS includes + footer
│   └── helpers.php                   h(), flash, csrf, badges
├── admin/
│   └── site-plans/
│       ├── index.php                 Project list + search
│       ├── create.php                New project form
│       ├── edit.php                  Main map editor (full-screen)
│       ├── export.php                Export preview + PDF options
│       └── api/
│           ├── save-object.php       Create/update drawn object
│           ├── delete-object.php     Soft/hard delete object
│           ├── save-measurement.php  Create/update measurement
│           ├── delete-measurement.php Delete measurement
│           ├── save-map-settings.php  Viewport + calibration
│           ├── save-parcel.php       Parcel boundary GeoJSON
│           ├── save-setbacks.php     Setback rules
│           ├── save-layer.php        Layer visibility
│           ├── load-project.php      Full project JSON for JS
│           ├── delete-project.php    HTMX delete project
│           └── export-pdf.php        Server-side PDF generation
├── assets/
│   ├── css/
│   │   └── site-plans.css           All custom styles
│   └── js/
│       ├── map-init.js              Leaflet init, basemap, geocode, calibration
│       ├── layer-manager.js         Layer group visibility management
│       ├── structure-tools.js       Render, select, drag, save structures
│       ├── drawing-tools.js         Tool mode switcher, polygon/line/text draw
│       ├── measurement-tools.js     Distance measurement draw + save
│       └── export-preview.js        Browser print-to-PDF permit sheet
├── exports/                         (writable) Server-generated PDFs
└── vendor/                          Composer packages (TCPDF / DomPDF)
```

---

## Map Navigation

| Key | Action |
|---|---|
| V | Select / Move tool |
| H | Pan mode |
| S | Place structure |
| M | Measure distance |
| P | Draw polygon |
| L | Draw line |
| T | Add text label |
| + / - | Zoom in / out |
| Esc | Cancel drawing |
| Ctrl+S | Save |
| Delete | Delete selected object |
| Dbl-click | Finish polygon / line |
| Right-click | Cancel current drawing |

---

## Workflow

1. **Create Project** → fill client info, address, parcel number, jurisdiction
2. **Open Editor** → map auto-centers on address
3. **Geocode** → type address in "Jump to" box if needed
4. **Draw Parcel Boundary** → use the parcel tool (purple) to trace property lines; double-click to close
5. **Apply Setbacks** → enter setback rules in left panel, click the border-outer button to draw setback overlay
6. **Place Structures** → click quick-add buttons (House, ADU, Garage, etc.) then click the map to place; drag to reposition
7. **Measure Distances** → activate Measure tool (M), click start point, click end point
8. **Label Objects** → click any object to select; edit label and properties in right panel
9. **Calibrate Scale** → click Calibrate, draw a line of known length, enter feet → scale stored per project
10. **Export PDF** → click Export PDF → use browser print or server PDF if library installed

---

## Basemap Options

| Option | Requires |
|---|---|
| **ESRI World Imagery** | Nothing — free, no key |
| **OpenStreetMap** | Nothing — free |
| **Mapbox Satellite** | `MAPBOX_TOKEN` in config |
| **Google Maps** | `GOOGLE_MAPS_KEY` in config |

ESRI is the default and works excellently for residential parcel work.

---

## Database Tables

| Table | Purpose |
|---|---|
| `projects` | Client/project master record |
| `map_settings` | Viewport center, zoom, basemap, scale calibration |
| `parcel_boundaries` | Property boundary GeoJSON |
| `site_plan_objects` | All drawn structures, lines, polygons, labels |
| `site_plan_measurements` | Distance measurement lines |
| `setback_rules` | Front/rear/side/accessory setback distances |
| `site_plan_layers` | Per-project layer visibility preferences |
| `permit_exports` | Export history log |
| `ai_analysis_queue` | Placeholder for future AI tasks |

---

## Future AI Integration

The `ai_analysis_queue` table and `AI_ENABLED` config flag are ready for:

- **Structure detection** — upload a sketch/photo, AI detects building footprints
- **Placement suggestions** — AI recommends where to position ADU given setbacks
- **Permit narrative** — AI writes the project description for the permit application
- **Missing item flags** — AI checks the plan against typical permit checklist
- **Scale auto-detection** — AI estimates scale from parcel size in satellite view

To enable, set `AI_ENABLED = true` and add `CLAUDE_API_KEY` or `OPENAI_API_KEY` to `config.php`, then implement a handler that reads from `ai_analysis_queue`, calls the API, and writes results back.

---

## Security Notes

- All DB queries use PDO prepared statements (no SQL injection)
- CSRF tokens on all state-changing forms and AJAX calls
- All output passed through `h()` (htmlspecialchars)
- API endpoints validate project ownership before mutations
- Set `DEBUG_MODE = false` in production

---

## Troubleshooting

**Map won't load** → Check browser console for tile errors. ESRI fallback requires no key — if it fails, check your server's outbound HTTPS access.

**Objects don't save** → Verify DB credentials in `config/config.php`. Check PHP error logs.

**PDF export fails** → Install TCPDF or DomPDF via Composer, or use browser print.

**Geocoding fails** → Nominatim (OpenStreetMap) is used for address lookup — no key required but may be rate-limited. For production consider adding a Mapbox or Google Geocoding API key.

**Calibration not saving** → Ensure `map_settings` table has a row for the project (auto-created on project create) and that `exports/` directory is writable.
