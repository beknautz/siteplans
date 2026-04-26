<?php
require_once dirname(__DIR__, 2) . '/includes/helpers.php';
session_start();

$page_title = 'Site Plan Projects';
$search  = trim($_GET['q'] ?? '');
$status  = $_GET['status'] ?? '';
$page    = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where  = ['1=1'];
$params = [];

if ($search !== '') {
    $where[]  = '(p.client_name LIKE :q OR p.project_name LIKE :q OR p.site_address LIKE :q OR p.parcel_number LIKE :q)';
    $params[':q'] = '%' . $search . '%';
}
if ($status !== '') {
    $where[]  = 'p.status = :status';
    $params[':status'] = $status;
}

$where_sql = implode(' AND ', $where);

$total = (int) db_row(
    "SELECT COUNT(*) cnt FROM projects p WHERE $where_sql",
    $params
)['cnt'];

$projects = db_rows(
    "SELECT p.*,
            (SELECT COUNT(*) FROM site_plan_objects spo WHERE spo.project_id = p.id) obj_count,
            (SELECT COUNT(*) FROM permit_exports pe WHERE pe.project_id = p.id) export_count
     FROM   projects p
     WHERE  $where_sql
     ORDER  BY p.updated_at DESC
     LIMIT  :limit OFFSET :offset",
    $params + [':limit' => $per_page, ':offset' => $offset]
);

$total_pages = (int) ceil($total / $per_page);

require_once dirname(__DIR__, 2) . '/includes/header.php';
?>
<div class="container-fluid py-4">
  <div class="d-flex align-items-center justify-content-between mb-3">
    <h4 class="mb-0"><i class="bi bi-map-fill text-warning me-2"></i>Site Plan Projects</h4>
    <a href="create.php" class="btn btn-primary btn-sm">
      <i class="bi bi-plus-circle me-1"></i> New Project
    </a>
  </div>

  <?= flash_html() ?>

  <!-- Search / Filter -->
  <form method="get" class="row g-2 mb-3">
    <div class="col-md-5">
      <div class="input-group input-group-sm">
        <span class="input-group-text"><i class="bi bi-search"></i></span>
        <input type="text" name="q" class="form-control"
               placeholder="Search client, project, address, parcel…"
               value="<?= h($search) ?>">
      </div>
    </div>
    <div class="col-md-3">
      <select name="status" class="form-select form-select-sm">
        <option value="">All Statuses</option>
        <?php foreach (['draft','active','submitted','approved','archived'] as $s): ?>
          <option value="<?= $s ?>" <?= $status === $s ? 'selected' : '' ?>>
            <?= ucfirst($s) ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="col-auto">
      <button class="btn btn-sm btn-outline-secondary" type="submit">Filter</button>
      <a href="index.php" class="btn btn-sm btn-link text-secondary">Clear</a>
    </div>
  </form>

  <!-- Project Table -->
  <div class="card shadow-sm">
    <div class="card-body p-0">
      <table class="table table-hover table-sm mb-0 align-middle">
        <thead class="table-dark">
          <tr>
            <th>#</th>
            <th>Client</th>
            <th>Project</th>
            <th>Address</th>
            <th>Parcel</th>
            <th>Type</th>
            <th>Status</th>
            <th>Objects</th>
            <th>Updated</th>
            <th class="text-end">Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($projects)): ?>
            <tr>
              <td colspan="10" class="text-center text-muted py-4">
                No projects found.
                <a href="create.php">Create one now.</a>
              </td>
            </tr>
          <?php else: ?>
            <?php foreach ($projects as $p): ?>
              <tr>
                <td class="text-secondary small"><?= $p['id'] ?></td>
                <td><?= h($p['client_name']) ?></td>
                <td class="fw-semibold"><?= h($p['project_name']) ?></td>
                <td class="small"><?= h($p['site_address']) ?>, <?= h($p['city']) ?></td>
                <td class="small font-monospace"><?= h($p['parcel_number']) ?></td>
                <td class="small"><?= project_type_label($p['project_type']) ?></td>
                <td><?= status_badge($p['status']) ?></td>
                <td class="text-center">
                  <span class="badge bg-secondary"><?= (int)$p['obj_count'] ?></span>
                </td>
                <td class="small text-secondary">
                  <?= date('m/d/Y', strtotime($p['updated_at'])) ?>
                </td>
                <td class="text-end">
                  <a href="edit.php?id=<?= $p['id'] ?>"
                     class="btn btn-sm btn-outline-primary py-0 px-2"
                     title="Open editor">
                    <i class="bi bi-pencil-map"></i> Edit
                  </a>
                  <a href="export.php?id=<?= $p['id'] ?>"
                     class="btn btn-sm btn-outline-success py-0 px-2"
                     title="Export PDF">
                    <i class="bi bi-file-earmark-pdf"></i>
                  </a>
                  <button class="btn btn-sm btn-outline-danger py-0 px-2"
                          title="Delete project"
                          hx-delete="api/delete-project.php?id=<?= $p['id'] ?>"
                          hx-confirm="Delete '<?= h(addslashes($p['project_name'])) ?>'? This cannot be undone."
                          hx-target="closest tr"
                          hx-swap="outerHTML">
                    <i class="bi bi-trash3"></i>
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>

    <?php if ($total_pages > 1): ?>
      <div class="card-footer d-flex justify-content-between align-items-center">
        <small class="text-secondary">
          Showing <?= ($offset + 1) ?>–<?= min($offset + $per_page, $total) ?> of <?= $total ?>
        </small>
        <nav>
          <ul class="pagination pagination-sm mb-0">
            <?php for ($i = 1; $i <= $total_pages; $i++): ?>
              <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                <a class="page-link"
                   href="?page=<?= $i ?>&q=<?= urlencode($search) ?>&status=<?= urlencode($status) ?>">
                  <?= $i ?>
                </a>
              </li>
            <?php endfor; ?>
          </ul>
        </nav>
      </div>
    <?php endif; ?>
  </div>
</div>

<?php require_once dirname(__DIR__, 2) . '/includes/footer.php'; ?>
