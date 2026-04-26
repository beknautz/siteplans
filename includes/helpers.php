<?php
// ============================================================
// Bootstrap — load config + database before any helper runs
// ============================================================
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

// Safe session start — won't crash on shared hosting with restricted session paths
function safe_session_start(): void {
    if (session_status() !== PHP_SESSION_NONE) return;
    // Suppress warnings; on shared hosts the save path may need no value (use default)
    @session_start();
}

// ============================================================
// General helper functions
// ============================================================

function h(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

function csrf_token(): string {
    safe_session_start();
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_check(): void {
    safe_session_start();
    $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(403);
        die('CSRF token mismatch');
    }
}

function flash_set(string $key, string $msg): void {
    safe_session_start();
    $_SESSION['flash'][$key] = $msg;
}

function flash_get(string $key): ?string {
    safe_session_start();
    $msg = $_SESSION['flash'][$key] ?? null;
    unset($_SESSION['flash'][$key]);
    return $msg;
}

function flash_html(): string {
    safe_session_start();
    if (empty($_SESSION['flash'])) return '';
    $html = '';
    foreach ($_SESSION['flash'] as $type => $msg) {
        $cls = match($type) {
            'success' => 'success',
            'error'   => 'danger',
            'warning' => 'warning',
            default   => 'info',
        };
        $html .= '<div class="alert alert-'.$cls.' alert-dismissible fade show" role="alert">'
               . h($msg)
               . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button>'
               . '</div>';
    }
    unset($_SESSION['flash']);
    return $html;
}

function project_type_label(string $type): string {
    return match($type) {
        'new_home'         => 'New Home',
        'adu'              => 'ADU',
        'garage'           => 'Garage',
        'shop'             => 'Shop / Steel Building',
        'remodel'          => 'Remodel',
        'addition'         => 'Addition',
        'site_development' => 'Site Development',
        default            => 'Other',
    };
}

function status_badge(string $status): string {
    [$cls, $label] = match($status) {
        'draft'     => ['secondary', 'Draft'],
        'active'    => ['primary',   'Active'],
        'submitted' => ['warning',   'Submitted'],
        'approved'  => ['success',   'Approved'],
        'archived'  => ['dark',      'Archived'],
        default     => ['light',     ucfirst($status)],
    };
    return '<span class="badge bg-'.$cls.'">'.h($label).'</span>';
}

function ensure_exports_dir(): bool {
    $dir = EXPORTS_DIR;
    if (!is_dir($dir)) {
        return mkdir($dir, 0755, true);
    }
    return is_writable($dir);
}

// ============================================================
// Authentication helpers
// ============================================================

function is_logged_in(): bool {
    safe_session_start();
    return !empty($_SESSION['user_id']);
}

function current_user(): ?array {
    safe_session_start();
    return $_SESSION['auth_user'] ?? null;
}

/**
 * Redirect to login if not authenticated.
 * Preserves the intended URL in ?next= so login can bounce back.
 */
function require_login(): void {
    if (!is_logged_in()) {
        $next = urlencode($_SERVER['REQUEST_URI'] ?? '');
        redirect(BASE_URL . '/auth/login.php' . ($next ? '?next=' . $next : ''));
    }
}

/**
 * Same guard but for JSON API endpoints — returns 401 instead of redirect.
 */
function require_login_api(): void {
    if (!is_logged_in()) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Not authenticated']);
        exit;
    }
}
