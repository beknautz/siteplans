<?php
require_once dirname(__DIR__) . '/config/config.php';
require_once dirname(__DIR__) . '/config/database.php';

$page_title = $page_title ?? APP_NAME;
$body_class  = $body_class  ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($page_title) ?> | <?= COMPANY ?></title>

  <!-- Bootstrap 5 -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
  <!-- Bootstrap Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Leaflet -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
  <!-- Leaflet Draw -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/leaflet.draw/1.0.4/leaflet.draw.css">
  <!-- App CSS -->
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/site-plans.css">
</head>
<body class="<?= htmlspecialchars($body_class) ?>">

<!-- Top Navigation -->
<nav class="navbar navbar-dark bg-dark navbar-expand-lg px-3 py-2">
  <a class="navbar-brand fw-bold d-flex align-items-center gap-2" href="<?= BASE_URL ?>/admin/site-plans/index.php">
    <i class="bi bi-map-fill text-warning"></i>
    <span><?= COMPANY ?></span>
    <small class="text-secondary fw-normal fs-6 ms-1">Site Plans</small>
  </a>
  <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#mainNav">
    <span class="navbar-toggler-icon"></span>
  </button>
  <div class="collapse navbar-collapse" id="mainNav">
    <ul class="navbar-nav me-auto">
      <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/admin/site-plans/index.php">
          <i class="bi bi-folder2-open"></i> Projects
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="<?= BASE_URL ?>/admin/site-plans/create.php">
          <i class="bi bi-plus-circle"></i> New Project
        </a>
      </li>
    </ul>
    <ul class="navbar-nav ms-auto align-items-center">
      <?php
      $__u = current_user();
      if ($__u): ?>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle d-flex align-items-center gap-1" href="#"
             data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-person-circle"></i>
            <span class="d-none d-md-inline"><?= htmlspecialchars($__u['name']) ?></span>
          </a>
          <ul class="dropdown-menu dropdown-menu-end">
            <li><span class="dropdown-item-text small text-muted"><?= htmlspecialchars($__u['email']) ?></span></li>
            <li><hr class="dropdown-divider my-1"></li>
            <?php if ($__u['role'] === 'admin'): ?>
            <li>
              <a class="dropdown-item" href="<?= BASE_URL ?>/admin/users/index.php">
                <i class="bi bi-people me-1"></i> Manage Users
              </a>
            </li>
            <li><hr class="dropdown-divider my-1"></li>
            <?php endif; ?>
            <li>
              <a class="dropdown-item text-danger" href="<?= BASE_URL ?>/auth/logout.php">
                <i class="bi bi-box-arrow-right me-1"></i> Sign Out
              </a>
            </li>
          </ul>
        </li>
      <?php else: ?>
        <li class="nav-item">
          <a class="nav-link" href="<?= BASE_URL ?>/auth/login.php">Sign In</a>
        </li>
      <?php endif; ?>
      <li class="nav-item ms-2">
        <span class="navbar-text text-secondary small"><?= htmlspecialchars(APP_VERSION) ?></span>
      </li>
    </ul>
  </div>
</nav>
