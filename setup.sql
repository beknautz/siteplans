-- ============================================================
-- BaceBuilt LLC - Permit Ready Site Plan Tool
-- Database Schema
-- ============================================================

CREATE DATABASE IF NOT EXISTS bacebuilt_siteplans
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE bacebuilt_siteplans;

-- ============================================================
-- projects
-- Core project / client record
-- ============================================================
CREATE TABLE IF NOT EXISTS projects (
  id               INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  client_name      VARCHAR(200)  NOT NULL DEFAULT '',
  project_name     VARCHAR(200)  NOT NULL DEFAULT '',
  site_address     VARCHAR(300)  NOT NULL DEFAULT '',
  city             VARCHAR(100)  NOT NULL DEFAULT '',
  state            VARCHAR(50)   NOT NULL DEFAULT 'WA',
  zip              VARCHAR(20)   NOT NULL DEFAULT '',
  parcel_number    VARCHAR(100)  NOT NULL DEFAULT '',
  jurisdiction     VARCHAR(150)  NOT NULL DEFAULT 'Yakima County',
  project_type     ENUM(
                     'new_home','adu','garage','shop','remodel',
                     'addition','site_development','other'
                   ) NOT NULL DEFAULT 'new_home',
  status           ENUM('draft','active','submitted','approved','archived')
                     NOT NULL DEFAULT 'draft',
  existing_summary TEXT          NULL,
  proposed_summary TEXT          NULL,
  notes            TEXT          NULL,
  created_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                     ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_parcel    (parcel_number),
  INDEX idx_status    (status),
  INDEX idx_created   (created_at)
) ENGINE=InnoDB;

-- ============================================================
-- map_settings
-- Stores viewport, basemap choice, calibration per project
-- ============================================================
CREATE TABLE IF NOT EXISTS map_settings (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id      INT UNSIGNED NOT NULL,
  center_lat      DECIMAL(10,7) NOT NULL DEFAULT 46.6021,
  center_lng      DECIMAL(10,7) NOT NULL DEFAULT -120.5059,
  zoom_level      TINYINT UNSIGNED NOT NULL DEFAULT 18,
  rotation        DECIMAL(6,2) NOT NULL DEFAULT 0.00,
  basemap         VARCHAR(50)  NOT NULL DEFAULT 'esri',
  calibration_px  DECIMAL(10,4) NULL COMMENT 'pixels for calibration line',
  calibration_ft  DECIMAL(10,4) NULL COMMENT 'real-world feet for calibration line',
  scale_factor    DECIMAL(12,6) NULL COMMENT 'ft per pixel calculated',
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_project (project_id),
  CONSTRAINT fk_mapsettings_project
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- parcel_boundaries
-- Stores the polygon defining the property boundary
-- ============================================================
CREATE TABLE IF NOT EXISTS parcel_boundaries (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id      INT UNSIGNED NOT NULL,
  boundary_type   ENUM('manual','geojson_import','gis_api')
                    NOT NULL DEFAULT 'manual',
  geojson         MEDIUMTEXT   NULL COMMENT 'GeoJSON FeatureCollection string',
  label           VARCHAR(200) NULL,
  lot_area_sqft   DECIMAL(12,2) NULL,
  lot_area_acres  DECIMAL(10,5) NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_pb_project (project_id),
  CONSTRAINT fk_pb_project
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- site_plan_objects
-- All drawn objects on the canvas: structures, lines, shapes, labels
-- ============================================================
CREATE TABLE IF NOT EXISTS site_plan_objects (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id      INT UNSIGNED NOT NULL,
  layer           VARCHAR(80)  NOT NULL DEFAULT 'structures'
                    COMMENT 'structures|setbacks|utilities|driveways|drainage|labels|measurements|parcel',
  object_type     VARCHAR(80)  NOT NULL
                    COMMENT 'rect|polygon|line|arrow|text|measurement|setback|utility|driveway|parking|drainage',
  structure_type  VARCHAR(80)  NULL
                    COMMENT 'house|adu|garage|shop|shed|deck|patio|well|septic|drain_field|meter|water_line|sewer_line|power_line|carport',
  label           VARCHAR(200) NULL,
  status          ENUM('existing','proposed','removed') NOT NULL DEFAULT 'proposed',
  -- Geometry stored as JSON array of {lat,lng} or canvas {x,y} points
  geometry_type   ENUM('latlng','canvas') NOT NULL DEFAULT 'latlng',
  coordinates     MEDIUMTEXT   NULL COMMENT 'JSON array of coordinate points',
  -- Bounding box helpers for quick queries
  anchor_lat      DECIMAL(10,7) NULL,
  anchor_lng      DECIMAL(10,7) NULL,
  -- Dimensions
  width_ft        DECIMAL(10,2) NULL,
  length_ft       DECIMAL(10,2) NULL,
  height_ft       DECIMAL(10,2) NULL,
  sqft            DECIMAL(10,2) NULL,
  rotation_deg    DECIMAL(6,2)  NOT NULL DEFAULT 0,
  -- Style
  fill_color      VARCHAR(20)   NOT NULL DEFAULT '#4A90D9',
  fill_opacity    DECIMAL(3,2)  NOT NULL DEFAULT 0.40,
  border_color    VARCHAR(20)   NOT NULL DEFAULT '#1A5276',
  border_width    TINYINT       NOT NULL DEFAULT 2,
  font_size       TINYINT       NOT NULL DEFAULT 12,
  -- Meta
  notes           TEXT          NULL,
  sort_order      SMALLINT      NOT NULL DEFAULT 0,
  visible         TINYINT(1)    NOT NULL DEFAULT 1,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_spo_project (project_id),
  INDEX idx_spo_layer   (layer),
  CONSTRAINT fk_spo_project
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- site_plan_measurements
-- Distance measurements drawn between two points on the map
-- ============================================================
CREATE TABLE IF NOT EXISTS site_plan_measurements (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id      INT UNSIGNED NOT NULL,
  label           VARCHAR(200) NULL,
  from_lat        DECIMAL(10,7) NULL,
  from_lng        DECIMAL(10,7) NULL,
  to_lat          DECIMAL(10,7) NULL,
  to_lng          DECIMAL(10,7) NULL,
  -- Canvas coords (used when geometry_type=canvas)
  from_x          DECIMAL(10,4) NULL,
  from_y          DECIMAL(10,4) NULL,
  to_x            DECIMAL(10,4) NULL,
  to_y            DECIMAL(10,4) NULL,
  geometry_type   ENUM('latlng','canvas') NOT NULL DEFAULT 'latlng',
  calculated_ft   DECIMAL(10,3) NULL COMMENT 'distance from map scale',
  override_ft     DECIMAL(10,3) NULL COMMENT 'manually entered override distance',
  display_ft      DECIMAL(10,3) NULL COMMENT 'final displayed distance',
  from_object_id  INT UNSIGNED NULL COMMENT 'optional snap reference',
  to_object_id    INT UNSIGNED NULL COMMENT 'optional snap reference',
  color           VARCHAR(20)   NOT NULL DEFAULT '#E74C3C',
  visible         TINYINT(1)    NOT NULL DEFAULT 1,
  created_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_spm_project (project_id),
  CONSTRAINT fk_spm_project
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- setback_rules
-- Jurisdiction setback requirements per project
-- ============================================================
CREATE TABLE IF NOT EXISTS setback_rules (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id      INT UNSIGNED NOT NULL,
  front_ft        DECIMAL(8,2) NOT NULL DEFAULT 20.00,
  rear_ft         DECIMAL(8,2) NOT NULL DEFAULT 5.00,
  side_ft         DECIMAL(8,2) NOT NULL DEFAULT 5.00,
  accessory_ft    DECIMAL(8,2) NOT NULL DEFAULT 5.00,
  well_sep_ft     DECIMAL(8,2) NOT NULL DEFAULT 100.00,
  septic_sep_ft   DECIMAL(8,2) NOT NULL DEFAULT 100.00,
  notes           TEXT         NULL,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
                    ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_sr_project (project_id),
  CONSTRAINT fk_sr_project
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- site_plan_layers
-- Per-project layer visibility preferences
-- ============================================================
CREATE TABLE IF NOT EXISTS site_plan_layers (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id      INT UNSIGNED NOT NULL,
  layer_key       VARCHAR(80)  NOT NULL,
  label           VARCHAR(120) NOT NULL,
  visible         TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order      TINYINT      NOT NULL DEFAULT 0,
  UNIQUE KEY uq_spl_proj_key (project_id, layer_key),
  CONSTRAINT fk_spl_project
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- permit_exports
-- Tracks each PDF export generated for a project
-- ============================================================
CREATE TABLE IF NOT EXISTS permit_exports (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id      INT UNSIGNED NOT NULL,
  export_type     ENUM('pdf','png','print') NOT NULL DEFAULT 'pdf',
  paper_size      ENUM('letter','ledger') NOT NULL DEFAULT 'letter',
  orientation     ENUM('landscape','portrait') NOT NULL DEFAULT 'landscape',
  file_path       VARCHAR(500) NULL COMMENT 'relative path to saved file if stored',
  notes           TEXT         NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_pe_project (project_id),
  CONSTRAINT fk_pe_project
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- ai_analysis_queue  (placeholder for future AI integration)
-- ============================================================
CREATE TABLE IF NOT EXISTS ai_analysis_queue (
  id              INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  project_id      INT UNSIGNED NOT NULL,
  task_type       VARCHAR(80)  NOT NULL
                    COMMENT 'detect_structures|suggest_placement|permit_narrative|flag_missing',
  input_data      MEDIUMTEXT   NULL COMMENT 'JSON payload sent to AI',
  output_data     MEDIUMTEXT   NULL COMMENT 'JSON response from AI',
  status          ENUM('pending','processing','complete','failed')
                    NOT NULL DEFAULT 'pending',
  model_used      VARCHAR(80)  NULL,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  completed_at    DATETIME     NULL,
  INDEX idx_aiq_project (project_id),
  INDEX idx_aiq_status  (status),
  CONSTRAINT fk_aiq_project
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- ============================================================
-- SEED DATA - Sample project for Yakima County, WA
-- ============================================================

INSERT INTO projects
  (client_name, project_name, site_address, city, state, zip,
   parcel_number, jurisdiction, project_type, status,
   existing_summary, proposed_summary, notes)
VALUES
  ('John & Mary Smith', 'Smith ADU Project',
   '1234 Orchard View Rd', 'Yakima', 'WA', '98908',
   '191330-21003', 'Yakima County',
   'adu', 'active',
   'Existing 1,800 sq ft single-family residence with attached 2-car garage. Property is 0.52 acres.',
   'Proposed 640 sq ft ADU (accessory dwelling unit) located in rear yard. Proposed gravel driveway extension.',
   'Yakima County permit application in progress. Setbacks per YCC 15A.09.');

INSERT INTO map_settings
  (project_id, center_lat, center_lng, zoom_level, basemap,
   calibration_px, calibration_ft, scale_factor)
VALUES
  (1, 46.6021000, -120.5059000, 18, 'esri', 100.0, 50.0, 0.5);

INSERT INTO setback_rules
  (project_id, front_ft, rear_ft, side_ft, accessory_ft,
   well_sep_ft, septic_sep_ft, notes)
VALUES
  (1, 20.00, 5.00, 5.00, 5.00, 100.00, 100.00,
   'Yakima County zoning R-1. Front setback measured from property line.');

INSERT INTO site_plan_layers
  (project_id, layer_key, label, visible, sort_order)
VALUES
  (1, 'satellite',   'Satellite Map',        1, 0),
  (1, 'parcel',      'Parcel Boundary',       1, 1),
  (1, 'existing',    'Existing Structures',   1, 2),
  (1, 'proposed',    'Proposed Structures',   1, 3),
  (1, 'setbacks',    'Setbacks',              1, 4),
  (1, 'measurements','Measurements',          1, 5),
  (1, 'utilities',   'Utilities',             1, 6),
  (1, 'labels',      'Labels',                1, 7),
  (1, 'driveways',   'Driveways / Access',    1, 8),
  (1, 'drainage',    'Drainage',              1, 9),
  (1, 'notes',       'Notes',                 0, 10);

-- Sample existing house object
INSERT INTO site_plan_objects
  (project_id, layer, object_type, structure_type, label, status,
   geometry_type, coordinates,
   anchor_lat, anchor_lng,
   width_ft, length_ft, sqft, rotation_deg,
   fill_color, fill_opacity, border_color, border_width)
VALUES
  (1, 'structures', 'rect', 'house', 'Existing House', 'existing',
   'latlng',
   '[{"lat":46.6022,"lng":-120.5060},{"lat":46.6022,"lng":-120.5057},{"lat":46.6020,"lng":-120.5057},{"lat":46.6020,"lng":-120.5060}]',
   46.6021000, -120.5059000,
   40.00, 45.00, 1800.00, 0.00,
   '#AED6F1', 0.60, '#1A5276', 2),

  (1, 'structures', 'rect', 'adu', 'Proposed ADU', 'proposed',
   'latlng',
   '[{"lat":46.6019,"lng":-120.5060},{"lat":46.6019,"lng":-120.5058},{"lat":46.6018,"lng":-120.5058},{"lat":46.6018,"lng":-120.5060}]',
   46.6018500, -120.5059000,
   20.00, 32.00, 640.00, 0.00,
   '#A9DFBF', 0.60, '#1E8449', 2);

INSERT INTO site_plan_measurements
  (project_id, label, from_lat, from_lng, to_lat, to_lng,
   geometry_type, calculated_ft, display_ft, color)
VALUES
  (1, 'House to Rear Property Line', 46.6020000, -120.5059000, 46.6018000, -120.5059000,
   'latlng', 22.00, 22.00, '#E74C3C'),
  (1, 'ADU to Side Property Line',   46.6018500, -120.5060000, 46.6018500, -120.5062000,
   'latlng', 8.00, 8.00, '#E74C3C');
